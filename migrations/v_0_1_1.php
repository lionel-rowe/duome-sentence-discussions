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

class v_0_1_1 extends base_migration
{
	protected $version = '0.1.1';

	public function effectively_installed()
	{
		return $this->is_up_to_date();
	}

	public static function depends_on()
	{
		return ['\luoning\sentencediscussions\migrations\v_0_1_0'];
	}

	public function update_schema()
	{
		if ($this->is_up_to_date()) {
			// explicitly return early to avoid side effects
			return [];
		}

		$table = $this->get_table_name();
		$topics_table = TOPICS_TABLE;

		$this->db->sql_query(
			"ALTER TABLE $table
				ADD CONSTRAINT `topic_id` FOREIGN KEY (`topic_id`)
				REFERENCES $topics_table (`topic_id`)
				ON DELETE CASCADE"
		);

		$this->update_version_number();

		return [];
	}
}
