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

abstract class base_migration extends \phpbb\db\migration\migration
{
	protected $name = 'sentence_discussions';

	/** @var string */
	protected $version;

	private function parse_version($version)
	{
		[$major, $minor, $patch] = array_map(
			function($x) {
				return (int) $x;
			},
			explode('.', $version)
		);

		return
			$major * 1e10 +
			$minor * 1e5 +
			$patch * 1e0;
	}

	protected function get_table_name()
	{
		return $this->table_prefix . $this->name;
	}

	protected function namespaced(string $str)
	{
		return $this->name . '_' . $str;
	}

	protected function update_version_number()
	{
		if (!$this->is_up_to_date()) {
			$this->config->set(
				$this->namespaced('version'),
				$this->version
			);
		}
	}

	protected function is_up_to_date()
	{
		return $this->parse_version(
			$this->config[$this->namespaced('version')] ?? '0.0.0'
		) >= $this->parse_version($this->version);
	}
}
