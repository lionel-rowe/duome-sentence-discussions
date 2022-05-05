<?php
/**
 *
 * Sentence Discussions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Lionel Rowe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace luoning\sentencediscussions;

/**
 * Sentence Discussions Service info.
 */
class service
{
	private $config;
	private $config_text;
	private $language;
	private $user;
	private $db;
	private $forum_id_mapping;

	public $table_prefix;
	public $sentence_discussions_table;
	public $name = 'sentence_discussions';

	/**
	 * Constructor
	 *
	 * @param \phpbb\user $user       User object
	 * @param string      $table_name The name of a db table
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\config\db_text $config_text,
		\phpbb\language\language $language,
		\phpbb\user $user,
		\phpbb\db\driver\driver_interface $db,
		string $table_prefix
	)
	{
		$this->config = $config;
		$this->config_text = $config_text;
		$this->language = $language;
		$this->user = $user;
		$this->table_prefix = $table_prefix;
		$this->db = $db;

		$this->sentence_discussions_table =
			$this->table_prefix . $this->name;

		$this->forum_id_mapping = $this->parse_forum_id_mapping($this->config_text->get(
			$this->namespaced('forum_id_mapping')
		));
	}

	public function namespaced(string $str)
	{
		return $this->name . '_' . $str;
	}

	/** @return int */
	public function get_forum_id(string $from_lang, string $learning_lang, $mapping = null)
	{
		$mapping = $mapping ? $mapping : $this->forum_id_mapping;

		$match = $mapping[$from_lang . '|' . $learning_lang];

		return $match ? $match : $mapping['xx|xx'];
	}

	public function get_deferred_forum_id_mapping_setter(
		string $text,
		array &$errors
	)
	{
		$result = $this->parse_forum_id_mapping($text, $errors);

		if (empty($errors)) {
			return function() use ($text, $result) {
				$this->config_text->set(
					$this->namespaced('forum_id_mapping'),
					$text
				);

				$this->forum_id_mapping = $result;
			};
		}

		return false;
	}

	private function parse_forum_id_mapping(string $text, array &$errors = [])
	{
		$mapping = [];

		// return this if parsing error occurs
		$default_mapping = [
			'xx|xx' => $this->config['forum_id'],
		];

		foreach (explode("\n", trim($text)) as $idx => $line) {
			if (empty(trim($line))) {
				continue;
			}

			$matcher = '/
				^
					([\w\-]+)        # from language
					\t
					([\w\-]+)        # learning language
					(?:
						\t
						(\d*)        # forum ID
						(?:\t.+)*    # any comments etc. that can be ignored
					)?
				$
			/x';

			$matches = [];
			preg_match($matcher, $line, $matches);

			$cells = array_slice($matches, 1);

			if (count($cells) < 2) {
				if ($idx === 0) {
					// is header row - ignore
					continue;
				}

				$errors[] = $this->language->lang(
					'ACP_SENTENCEDISCUSSIONS_ERR_LINE_HAS_TOO_FEW_COLUMNS',
					$line
				);

				continue;
			}

			[$from_lang, $learning_lang] = array_map('trim', $cells);

			$forum_id = isset($cells[2]) ? (int) $cells[2] : 0;

			if (!$from_lang || !$learning_lang) {
				$errors[] = $this->language->lang(
					'ACP_SENTENCEDISCUSSIONS_ERR_LINE_NO_LANGUAGE_PAIR',
					$line
				);

				continue;
			}

			$mapping[$from_lang . '|' . $learning_lang] = $forum_id;
		}

		if (!$mapping['xx|xx']) {
			$errors[] = $this->language->lang(
				'ACP_SENTENCEDISCUSSIONS_ERR_MISSING_FALLBACK_LANGUAGE_PAIR'
			);
		}

		$specified_forum_ids = array_filter(array_unique(array_values($mapping)));

		if (!count($specified_forum_ids)) {
			$errors[] = $this->language->lang(
				'ACP_SENTENCEDISCUSSIONS_ERR_NO_FORUM_IDS_SPECIFIED'
			);

			return $default_mapping;
		}

		$forums_table = $this->table_prefix . 'forums';

		$forum_id_matches = $this->db->sql_in_set('forum_id', $specified_forum_ids);

		$result = $this->db->sql_query(
			"SELECT forum_id
				FROM $forums_table
				WHERE $forum_id_matches"
		);

		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$found_forum_ids = array_map(
			function($row) {
				return (int) $row['forum_id'];
			},
			$rows
		);

		foreach ($specified_forum_ids as $forum_id) {
			if (!in_array($forum_id, $found_forum_ids)) {
				$errors[] = $this->language->lang(
					'ACP_SENTENCEDISCUSSIONS_ERR_FORUM_ID_NOT_FOUND',
					$forum_id
				);
			}
		}

		if (!$mapping['xx|xx']) {
			$errors[] = $this->language->lang(
				'ACP_SENTENCEDISCUSSIONS_ERR_MISSING_FALLBACK_LANGUAGE_PAIR'
			);
		}

		return empty($errors) ? $mapping : $default_mapping;
	}

}
