<?php
/**
 *
 * Sentence Discussions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Lionel Rowe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace luoning\sentencediscussions\acp;

/**
 * Sentence Discussions ACP module info.
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\luoning\sentencediscussions\acp\main_module',
			'title'		=> 'ACP_SENTENCEDISCUSSIONS_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_SENTENCEDISCUSSIONS',
					'auth'	=> 'ext_luoning/sentencediscussions && acl_a_board',
					'cat'	=> ['ACP_SENTENCEDISCUSSIONS_TITLE'],
				],
			],
		];
	}
}
