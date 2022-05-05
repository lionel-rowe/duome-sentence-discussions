<?php
/**
 *
 * Sentence Discussions. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Lionel Rowe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, [
	'SENTENCEDISCUSSIONS_EVENT' => ' :: Sentencediscussions Event :: ',

	// ACP
	'ACP_SENTENCEDISCUSSIONS_FORUM_ID' => 'Unanswered Forum ID',
	'ACP_SENTENCEDISCUSSIONS_FORUM_ID_DESC' => 'Forum ID of the forum for unanswered sentence discussions',

	'ACP_SENTENCEDISCUSSIONS_FORUM_ID_MAPPING' => 'Forum ID mapping',
	'ACP_SENTENCEDISCUSSIONS_FORUM_ID_MAPPING_DESC' => 'Mapping of language pairs to forum IDs - copy over updates from <a href="https://docs.google.com/spreadsheets/d/1WM8OB_jmZLm1yts3XEy5n2xCWHxhmhG_tf8arN8pfvU/edit#gid=0" target="_blank">the Google Sheet (link)</a>',
	'ACP_SENTENCEDISCUSSIONS_FORUM_ID_MAPPING_DESC_PARSED' => 'Parsed',

	'ACP_SENTENCEDISCUSSIONS_FORUM_ID_MAPPING_PARSE_ERR' => 'Could not parse forum ID mapping',

	'ACP_SENTENCEDISCUSSIONS_FORUM_ID_MAPPING_PARSE_ERR' => 'Could not parse forum ID mapping',
	'ACP_SENTENCEDISCUSSIONS_ERR_LINE_HAS_TOO_FEW_COLUMNS'
		=> 'Line "%s" has too few columns',
	'ACP_SENTENCEDISCUSSIONS_ERR_LINE_NO_LANGUAGE_PAIR'
		=> 'Line "%s" does not have a full language pair',
	'ACP_SENTENCEDISCUSSIONS_ERR_MISSING_FALLBACK_LANGUAGE_PAIR'
		=> 'Missing a fallback language pair ("xx" to "xx")',
	'ACP_SENTENCEDISCUSSIONS_ERR_FORUM_ID_NOT_FOUND'
		=> 'No forum found for forum ID %s',
	'ACP_SENTENCEDISCUSSIONS_ERR_NO_FORUM_IDS_SPECIFIED'
		=> 'No forum IDs specified',

	'ACP_SENTENCEDISCUSSIONS_SETTING_SAVED' => 'Settings have been saved successfully!',

	// user-facing
	'SENTENCEDISCUSSIONS_ERROR_TITLE' => 'Error: %s',
	'SENTENCEDISCUSSIONS_CREATE_TOPIC_FAILED_ERROR_TITLE' => 'Failed to create topic',
	'SENTENCEDISCUSSIONS_CREATE_TOPIC_FAILED_ERROR_MESSAGE' => '
		<p>
			Request data was invalid for creating a topic. Please <a class="postlink" href="https://forum.duome.eu/ucp.php?i=pm&mode=compose&u=66" target="_blank">contact the extension’s maintainer</a>, copying and pasting the debug information below into your message:
		</p>
		<textarea readonly onclick="this.select()">%1$s</textarea>
	',

	'SENTENCEDISCUSSIONS_LOGIN_EXPLAIN' => 'You need to be logged in to use sentence discussions.',
]);
