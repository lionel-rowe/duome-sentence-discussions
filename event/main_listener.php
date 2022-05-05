<?php
/**
 *
 * Sentence Discussions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Lionel Rowe, https://github.com/lionel-rowe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace luoning\sentencediscussions\event;

/**
 * @ignore
 */

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.submit_post_end' => 'move_to_forum',
			'core.posting_modify_submit_post_before' =>
				'allow_emojis_in_guest_name',
		];
	}

	protected $config;
	protected $language;
	protected $template;
	protected $user;
	protected $service;
	protected $db;
	protected $table_prefix;
	protected $php_ext;

	/**
	 * Constructor
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\luoning\sentencediscussions\service $service,
		\phpbb\db\driver\driver_interface $db,
		string $table_prefix,
		string $php_ext
	)
	{
		$this->config = $config;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->service = $service;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->php_ext = $php_ext;
	}

	/**
	 * Load common language files during user setup
	 */
	public function load_language_on_setup(\phpbb\event\data $event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'luoning/sentencediscussions',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	protected function decode($str) {
		// values as generated in `sentence-discussions.js`
		$re = '/([\x{e000}-\x{e3ff}])([\x{e400}-\x{e7ff}])/u';

		return preg_replace_callback(
			$re,
			function ($matches) {
				// see https://stackoverflow.com/questions/43564445/how-to-map-unicode-codepoints-from-an-utf-16-file-using-c/43564563#43564563
				// no need to subtract diff, as 0x3ff has no overlap with 0x800
				$codepoint = ((mb_ord($matches[1]) & 0x3ff) << 10)
					| (mb_ord($matches[2]) & 0x3ff)
					| 0x10000;

				return mb_chr($codepoint, 'utf-8');
			},
			$str
		);
	}

	public function move_to_forum(\phpbb\event\data $event)
	{
		$topic_id = $event['data']['topic_id'];
		$topic_last_post_id = $event['data']['topic_last_post_id'];
		$sentence_discussions_table = $this->service->sentence_discussions_table;

		if ($topic_last_post_id === $topic_id) {
			return;
		}

		$result = $this->db->sql_query(
			"SELECT from_lang, learning_lang
				FROM $sentence_discussions_table
				WHERE topic_id = $topic_id"
		);

		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!function_exists('move_topics'))
		{
			include($phpbb_root_path . 'includes/functions_admin.' . $this->php_ext);
		}

		if ($row) {
			$forum_id = $this->service->get_forum_id(
				$row['from_lang'], $row['learning_lang']
			);

			move_topics([(int) $topic_id], $forum_id);
		}
	}

	/**
	 * core.posting_modify_submit_post_before
	 *
	 * Allow emojis in guest name, e.g. "sentence bot 🤖", e.g. on topic edit by
	 * mods
	 */
	public function allow_emojis_in_guest_name(\phpbb\event\data $event)
	{
		$event['post_author_name'] = utf8_encode_ucr($this->decode(
			$event['post_author_name']
		));

		return $event;
	}
}
