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

class v_0_1_10 extends base_migration
{
	protected $version = '0.1.10';

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists(
			$this->get_table_name(),
			'id'
		);
	}

	public static function depends_on()
	{
		return [
			'\luoning\sentencediscussions\migrations\v_0_1_1'
		];
	}

	public function update_schema()
	{
		$table = $this->get_table_name();

		$this->db->sql_query(
			"ALTER TABLE $table
				DROP PRIMARY KEY,
				ADD id INT NOT NULL AUTO_INCREMENT,
				ADD PRIMARY KEY (id)"
		);

		$this->update_version_number();

		return [];
	}

	public function update_data()
	{
		return [
			['config_text.add', [
				$this->namespaced('forum_id_mapping'),
				"xx\txx\t" . $this->config[$this->namespaced('forum_id')],
			]],
		];
	}

	public function revert_data()
	{
		return ['config_text.remove', [
			$this->namespaced('forum_id_mapping'),
		]];
	}
}
