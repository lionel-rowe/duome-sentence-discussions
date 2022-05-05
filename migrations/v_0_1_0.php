<?php
/**
 *
 * Sentence Discussions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Lionel Rowe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace luoning\sentencediscussions\migrations;

class v_0_1_0 extends base_migration
{
	protected $version = '0.1.0';

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->get_table_name())
			&& isset($this->config[$this->namespaced('forum_id')]);
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->get_table_name() => [
					'COLUMNS' => [
						'challenge_generator_id' => ['VCHAR:255', ''],
						'sentence_discussion_id' => ['VCHAR:255', ''],
						'duolingo_forum_topic_id' => ['UINT', 0],

						'from_lang' => ['VCHAR:255', ''],
						'learning_lang' => ['VCHAR:255', ''],

						'pathname' => ['VCHAR:255', ''],

						'from_sentence' => ['TEXT', ''],
						'from_sentence_alternatives' => ['TEXT', ''],
						'learning_sentence' => ['TEXT', ''],
						'learning_sentence_alternatives' => ['TEXT', ''],

						'topic_id' => ['UINT', 0],
						'creation_triggered_by_user_id' => ['UINT', 0],
					],
					'PRIMARY_KEY'	=> ['challenge_generator_id'],
				],
			],
			'add_index' => [
				$this->get_table_name() => [
					'topic_id' => ['topic_id'],
					'challenge_generator_id' => ['challenge_generator_id'],
					'from_lang' => ['from_lang'],
					'learning_lang' => ['learning_lang'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [$this->get_table_name()],
		];
	}

	public function update_data()
	{
		return [
			['config.add', [$this->namespaced('forum_id'), -1]],

			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_SENTENCEDISCUSSIONS_TITLE'
			]],
			['module.add', [
				'acp',
				'ACP_SENTENCEDISCUSSIONS_TITLE',
				[
					'module_basename'	=> '\luoning\sentencediscussions\acp\main_module',
					'modes'				=> ['settings'],
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', [$this->namespaced('forum_id')]],
		];
	}
}
