<?php
/**
 *
 * Sentence Discussions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Lionel Rowe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace luoning\sentencediscussions\controller;

use Symfony\Component\HttpFoundation\Response;

function coerce_to_string_array($obj) {
	return array_filter(explode("\0", implode("\0", $obj)));
}

/**
 * Sentence Discussions main controller.
 */
class main_controller
{
	protected $config;
	protected $helper;
	protected $template;
	protected $language;
	protected $user;
	protected $service;
	protected $auth;
	protected $request;
	protected $symfony_request;
	protected $db;
	protected $phpbb_dispatcher;
	protected $php_ext;
	protected $table_prefix;
	protected $phpbb_root_path;
	protected $sentence_discussions_table;

	/**
	 * Constructor
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\template\template $template,
		\phpbb\language\language $language,
		\phpbb\user $user,
		\luoning\sentencediscussions\service $service,
		\phpbb\auth\auth $auth,
		\phpbb\request\request $request,
		\phpbb\symfony_request $symfony_request,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\event\dispatcher_interface $phpbb_dispatcher,
		string $php_ext,
		string $table_prefix,
		string $phpbb_root_path
	)
	{
		$this->config			= $config;
		$this->helper			= $helper;
		$this->template			= $template;
		$this->language			= $language;
		$this->user				= $user;
		$this->service			= $service;
		$this->auth				= $auth;
		$this->request			= $request;
		$this->symfony_request	= $symfony_request;
		$this->db				= $db;
		$this->phpbb_dispatcher	= $phpbb_dispatcher;
		$this->php_ext			= $php_ext;
		$this->table_prefix		= $table_prefix;
		$this->phpbb_root_path	= $phpbb_root_path;

		$this->sentence_discussions_table =
			$this->table_prefix . 'sentence_discussions';

		// Add our common language file
		$this->language->add_lang('common', 'luoning/sentencediscussions');
	}

	/**
	 * Creates a message
	 */
	protected function create_message(string $message)
	{
		include_once($this->phpbb_root_path . 'includes/message_parser.' . $this->php_ext);

		$message_parser = new \parse_message($message);
		$message_parser->parse(true, true, true, true, false);

		return $message_parser->message;
	}

	/**
	 * Redirect to topic by topic ID
	 */
	protected function go_to_topic(int $topic_id)
	{
		return redirect(append_sid(
			"{$this->phpbb_root_path}viewtopic.$this->php_ext",
			['t' => $topic_id],
			false
		));
	}

	/**
	 * Get existing topic
	 */
	protected function get_topic(string $challenge_generator_id)
	{
		$safe_id = $this->db->sql_escape($challenge_generator_id);

		$result = $this->db->sql_query(
			"SELECT `topic_id` FROM $this->sentence_discussions_table
				WHERE `challenge_generator_id` = '$safe_id'"
		);

		$existing_topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $existing_topic;
	}

	/**
	 * Renders an error response
	 */
	protected function render_error_response(string $title, string $message, int $status_code)
	{
		$title = $this->language->lang(
			'SENTENCEDISCUSSIONS_ERROR_TITLE',
			$title
		);

		$this->template->assign_vars([
			'SENTENCEDISCUSSIONS_ERROR_TITLE' => $title,
			'SENTENCEDISCUSSIONS_ERROR' => $message,
		]);

		return $this->helper->render(
			'@luoning_sentencediscussions/sentencediscussions_error.html',
			$title,
			$status_code
		);
	}

	protected function html_safe(string $str)
	{
		return utf8_encode_ucr(htmlspecialchars($str));
	}

	/**
	 * Create new topic
	 */
	protected function create_new_topic(object $raw_data)
	{
		$posts_table = POSTS_TABLE;
		$forums_table = FORUMS_TABLE;
		$topics_table = TOPICS_TABLE;

		$challenge_generator_id = (string) $raw_data->challengeGeneratorId;
		$duolingo_forum_topic_id = (int) $raw_data->duolingoForumTopicId;
		$from_lang = (string) $raw_data->fromLang;
		$from_sentence = (string) $raw_data->fromSentence;
		$learning_lang = (string) $raw_data->learningLang;
		$learning_sentence = (string) $raw_data->learningSentence;
		$pathname = (string) $raw_data->pathname;
		$sentence_discussion_id = (string) $raw_data->sentenceDiscussionId;
		$user_id = (int) $raw_data->userId;
		$from_sentence_alternatives =
			coerce_to_string_array($raw_data->fromSentenceAlternatives);
		$learning_sentence_alternatives =
			coerce_to_string_array($raw_data->learningSentenceAlternatives);

		if (!$challenge_generator_id
			|| preg_match('/[^0-9a-f]/i', $challenge_generator_id)) {
			// is malformed request
			return false;
		}

		$this->db->sql_transaction('begin');

		/* === TOPIC === */

		$time = time();
		$title = $this->html_safe("$learning_sentence ($from_lang → $learning_lang)");
		$bot_user_id = ANONYMOUS;
		$bot_username = $this->html_safe('sentence bot 🤖');

		$post_text_raw = implode("\n", array_filter([
				"[sent_disc id=$challenge_generator_id]",
				"## $learning_sentence",
				$from_sentence ? "_$from_lang: {$from_sentence}_" : null,
				$duolingo_forum_topic_id
					? "\nDuolingo forum topic: https://forum.duolingo.com/comment/$duolingo_forum_topic_id\n"
					: null,
				'[/sent_disc]',
			],
			'is_string'
		));

		$post_text = $this->create_message($post_text_raw);
		$post_md5 = md5($post_text_raw);

		$forum_id = (int) $this->config[$this->service->namespaced('forum_id')];

		$topic_data = [
			'topic_poster' => $bot_user_id,
			'forum_id' => $forum_id,
			'topic_title' => $title,
			'topic_time' => $time,
			'topic_last_post_time' => $time,
			'topic_visibility' => 1,
			'topic_first_poster_name' => $bot_username,
			'topic_last_poster_name' => $bot_username,
			'topic_last_post_subject' => $title,
			'topic_posts_approved' => 1,
		];

		$this->db->sql_query(
			'INSERT INTO '
				. $topics_table
				. ' '
				. $this->db->sql_build_array('INSERT', $topic_data)
		);

		$topic_id = (int) $this->db->sql_nextid();

		/* === CHALLENGE === */

		$challenge_data = [
			'challenge_generator_id' => $challenge_generator_id,
			'duolingo_forum_topic_id' => $duolingo_forum_topic_id,
			'from_lang' => $from_lang,
			'from_sentence' => $from_sentence,
			'from_sentence_alternatives' => json_encode(
				$from_sentence_alternatives,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			'learning_lang' => $learning_lang,
			'learning_sentence' => $learning_sentence,
			'learning_sentence_alternatives' => json_encode(
				$learning_sentence_alternatives,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			'pathname' => $pathname,
			'sentence_discussion_id' => $sentence_discussion_id,
			'topic_id' => $topic_id,
			'creation_triggered_by_user_id' => $user_id,
		];

		$this->db->sql_query(
			'INSERT INTO '
				. $this->sentence_discussions_table
				. ' '
				. $this->db->sql_build_array('INSERT', $challenge_data)
		);

		/* === POST === */

		$post_data = [
			'topic_id' => $topic_id,
			'forum_id' => $forum_id,
			'poster_id' => $bot_user_id,
			'post_username' => $bot_username,

			'poster_ip' => '0.0.0.0',
			'post_time' => $time,

			'post_subject' => $title,
			'post_text' => $post_text,

			'post_checksum' => $post_md5,

			'post_visibility' => 1,
		];

		$this->db->sql_query(
			'INSERT INTO '
				. $posts_table
				. ' '
				. $this->db->sql_build_array('INSERT', $post_data)
		);

		$post_id = (int) $this->db->sql_nextid();

		/* === FORUM METADATA === */

		$forum_metadata = [
			'forum_last_post_id' => $post_id,
			'forum_last_poster_id' => $bot_user_id,
			'forum_last_post_subject' => $title,
			'forum_last_post_time' => $time,
			'forum_last_poster_name' => $bot_username,
		];

		$this->db->sql_query(
			"UPDATE $forums_table
				SET `forum_topics_approved` = `forum_topics_approved` + 1,
					`forum_posts_approved` = `forum_posts_approved` + 1,"
				. $this->db->sql_build_array('UPDATE', $forum_metadata)
				. "WHERE `forum_id` = $forum_id"
		);

		/* === TOPIC METADATA === */

		$this->db->sql_query(
			"UPDATE $topics_table
				SET `topic_last_post_id` = $post_id,
					`topic_first_post_id` = $post_id
				WHERE `topic_id` = $topic_id"
		);

		/* === COMMIT === */

		$this->db->sql_transaction('commit');

		/* === UPDATE SEARCH INDEX === */

		$error = false;

		$search_type = $this->config['search_type'];

		$search = new $search_type(
			$error,
			$this->phpbb_root_path,
			$this->php_ext,
			$this->auth,
			$this->config,
			$this->db,
			$this->user,
			$this->phpbb_dispatcher
		);

		if ($error)
		{
			trigger_error($error);
		}

		$search->index('post', $post_id, $post_text, $title, $bot_user_id, $forum_id);

		return true;
	}

	protected function parse_data(string $b64_data)
	{
		return json_decode(
			'{' . base64_decode(strtr($b64_data, '-_', '+/')) . '}'
		);
	}

	/**
	 * Controller handler for route /sentence-discussions/{b64_data}
	 */
	public function handle(string $b64_data)
	{
		$user_id = (int) $this->user->data['user_id'];

		if ($user_id === ANONYMOUS)
		{
			return login_box(
				"sentence-discussions/$b64_data",
				$this->language->lang('SENTENCEDISCUSSIONS_LOGIN_EXPLAIN')
			);
		}

		$raw_data = $this->parse_data($b64_data);

		$raw_data->userId = $user_id;

		$challenge_generator_id = $raw_data->challengeGeneratorId;

		$topic = $this->get_topic($challenge_generator_id);

		if (!$topic) {
			$this->create_new_topic($raw_data);
			$topic = $this->get_topic($challenge_generator_id);
		}

		if (!$topic) {
			return $this->render_error_response(
				$this->language->lang(
					'SENTENCEDISCUSSIONS_CREATE_TOPIC_FAILED_ERROR_TITLE'
				),
				$this->language->lang(
					'SENTENCEDISCUSSIONS_CREATE_TOPIC_FAILED_ERROR_MESSAGE',
					htmlspecialchars(implode(
						"\n",
						[
							'=== START DEBUG INFO ===',
							$b64_data,
							'=== END DEBUG INFO ===',
						]
					))
				),
				Response::HTTP_BAD_REQUEST
			);
		}

		return $this->go_to_topic($topic['topic_id']);
	}
}
