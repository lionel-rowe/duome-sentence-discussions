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

/**
 * Sentence Discussions ACP controller.
 */
class acp_controller
{
	private $config;
	private $config_text;
	private $language;
	private $log;
	private $request;
	private $template;
	private $user;
	private $service;
	private $db;
	private $php_ext;

	/** @var string Custom form action */
	private $u_action;

	/**
	 * Constructor.
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\config\db_text $config_text,
		\phpbb\language\language $language,
		\phpbb\log\log $log,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\luoning\sentencediscussions\service $service,
		\phpbb\db\driver\driver_interface $db,
		string $php_ext
	)
	{
		$this->config	= $config;
		$this->config_text = $config_text;
		$this->language	= $language;
		$this->log		= $log;
		$this->request	= $request;
		$this->template	= $template;
		$this->user		= $user;
		$this->service	= $service;
		$this->db	= $db;
		$this->php_ext	= $php_ext;
	}

	private function handle_forum_id_mapping()
	{
		$topics_table = $this->service->table_prefix . 'topics';
		$sentence_discussions_table = $this->service->sentence_discussions_table;

		$result = $this->db->sql_query(
			"SELECT from_lang, learning_lang FROM $sentence_discussions_table
				GROUP BY from_lang, learning_lang"
		);

		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($rows as $row) {
			$from_lang = $row['from_lang'];
			$learning_lang = $row['learning_lang'];

			$forum_id = $this->service->get_forum_id($from_lang, $learning_lang);

			$result = $this->db->sql_query(
				"SELECT s.topic_id FROM $sentence_discussions_table s
					JOIN $topics_table t
						ON s.topic_id = t.topic_id
					WHERE s.from_lang = '$from_lang'
						AND s.learning_lang = '$learning_lang'
						AND t.topic_first_post_id <> t.topic_last_post_id"
			);

			$topic_ids = array_map(
				function($row) {
					return $row['topic_id'];
				},
				$this->db->sql_fetchrowset($result)
			);

			$this->db->sql_freeresult($result);

			if (!function_exists('move_topics'))
			{
				include($phpbb_root_path . 'includes/functions_admin.' . $this->php_ext);
			}

			// chunk to avoid stack overflow etc. due to huge IN clauses if lots
			// of affected topics
			// https://stackoverflow.com/questions/1869753/maximum-size-for-a-sql-server-query-in-clause-is-there-a-better-approach
			$chunks = array_chunk($topic_ids, 1e4);

			foreach ($chunks as $chunk) {
				move_topics($chunk, $forum_id);
			}
		}
	}

	/**
	 * Display the options a user can configure for this extension.
	 *
	 * @return void
	 */
	public function display_options()
	{
		// Add our common language file
		$this->language->add_lang('common', 'luoning/sentencediscussions');

		// Create a form key for preventing CSRF attacks
		add_form_key('luoning_sentencediscussions_acp');

		// Create an array to collect errors that will be output to the user
		$errors = [];

		// Is the form being submitted to us?
		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!check_form_key('luoning_sentencediscussions_acp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}

			$forum_id_mapping = $this->request->variable(
				'sentence_discussions_forum_id_mapping',
				'',
				true
			);

			$forum_id_mapping_setter = $this->service->
				get_deferred_forum_id_mapping_setter(
					$forum_id_mapping,
					$errors
				);

			// If no errors, process the form data
			if (empty($errors))
			{
				// Set the options the user configured
				$this->config->set(
					'sentence_discussions_forum_id',
					$this->request->variable(
						'sentence_discussions_forum_id',
						-1
					)
				);

				$forum_id_mapping_setter();

				$this->handle_forum_id_mapping();

				// Add option settings change action to the admin log
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_SENTENCEDISCUSSIONS_SETTINGS');

				// Option settings have been updated and logged
				// Confirm this to the user and provide link back to previous page
				trigger_error($this->language->lang('ACP_SENTENCEDISCUSSIONS_SETTING_SAVED') . adm_back_link($this->u_action));
			}
		}

		$s_errors = !empty($errors);

		// Set output variables for display in the template
		$this->template->assign_vars([
			'S_ERROR'		=> $s_errors,
			'ERROR_MSG'		=> $s_errors ? implode('<br />', $errors) : '',

			'U_ACTION'		=> $this->u_action,

			'SENTENCE_DISCUSSIONS_FORUM_ID'	=> (int) $this->config['sentence_discussions_forum_id'],
			'SENTENCE_DISCUSSIONS_FORUM_ID_MAPPING'	=> $this->config_text->get('sentence_discussions_forum_id_mapping'),
		]);
	}

	/**
	 * Set custom form action.
	 *
	 * @param string	$u_action	Custom form action
	 * @return void
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
