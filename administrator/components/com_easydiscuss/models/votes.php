<?php
/**
* @package      EasyDiscuss
* @copyright    Copyright (C) 2010 - 2015 Stack Ideas Sdn Bhd. All rights reserved.
* @license      GNU/GPL, see LICENSE.php
* Komento is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined('_JEXEC') or die('Restricted access');

require_once dirname( __FILE__ ) . '/model.php';

class EasyDiscussModelVotes extends EasyDiscussAdminModel
{
	/**
	 * Check if a user vote exists in the system.
	 *
	 * @since	4.0
	 * @param	int		The unique post id.
	 * @param	int 	The user's unique id.
	 * @param	string	The user's ip address.
	 * @param	string	The unique session id.
	 * @return	boolean	True if user has already voted.
	 */
	public function hasVoted($postId, $userId = null, $sessionId = null)
	{
		$db = $this->db;
		$query = 'SELECT COUNT(1) FROM ' . $db->nameQuote('#__discuss_votes');

		if ($userId) {
			$query	.= ' WHERE ' . $db->nameQuote('user_id') . '=' . $db->Quote($userId);
			$query	.= ' AND ' . $db->nameQuote('post_id') . '=' . $db->Quote($postId);
		} else {
			$query	.= ' WHERE ' . $db->nameQuote('post_id') . '=' . $db->Quote($postId);
			$query	.= ' AND ' . $db->nameQuote('session_id') . '=' . $db->Quote($sessionId);
		}

		$db->setQuery($query);

		$voted = $db->loadResult() ? true : false;

		return $voted;
	}

	/**
	 * Gets the vote type.
	 *
	 * @since	4.0
	 * @access	public
	 * @param	int		The unique post id.
	 * @param	int		The user's unique id.
	 * @param	string	The unique session id.
	 */
	public function getVoteType($postId, $userId = null, $sessionId = null)
	{
		$db = $this->db;
		$query = 'SELECT ' . $db->nameQuote('value') . ' FROM ' . $db->nameQuote('#__discuss_votes');

		if ($userId) {
			$query 	.= ' WHERE ' . $db->nameQuote('user_id') . '=' . $db->Quote($userId);
		} else {
			$query 	.= ' WHERE ' . $db->nameQuote('session_id') . '=' . $db->Quote($sessionId);
		}

		$query 	.= ' AND ' . $db->nameQuote('post_id') . '=' . $db->Quote($postId);
		$db->setQuery($query);
		$result	= $db->loadResult();

		return $result;
	}

	/**
	 * Check whether the current vote is it modifying
	 * 
	 * @since	4.0.6
	 * @access	public
	 * @param	int		The unique post id.
	 * @param	int		The user's unique id.
	 * @param	string	The unique session id.
	 * @param	int		The unique vote value.
	 */
	public function voteModifying($postId, $userId = null, $sessionId = null)
	{
		$db = $this->db;
	
		// Determine this vote is it modifying
		$query  = 'SELECT COUNT(1) FROM ' . $db->nameQuote('#__discuss_votes');
		$query .= ' WHERE ' . $db->nameQuote('session_id') . '=' . $db->Quote($sessionId);
		$query .= ' AND ' . $db->nameQuote('user_id') . '=' . $db->Quote($userId);
		$query .= ' AND ' . $db->nameQuote('post_id') . '=' . $db->Quote($postId);

		$db->setQuery($query);
		$result = $db->loadResult() ? true : false;

		return $result;
	}

	/**
	 * Undo the user current voting
	 * 
	 * @since	4.0.6
	 * @access	public
	 * @param	int		The unique post id.
	 * @param	int		The user's unique id.
	 * @param	string	The unique session id.
	 */
	public function undoVote($postId, $userId = null, $sessionId = null)
	{
		$db = $this->db;

		// Delete the current vote record 
		$query  = 'DELETE FROM ' . $db->nameQuote('#__discuss_votes');
		$query .= ' WHERE ' . $db->nameQuote('session_id') . '=' . $db->Quote($sessionId);
		$query .= ' AND ' . $db->nameQuote('user_id') . '=' . $db->Quote($userId);
		$query .= ' AND ' . $db->nameQuote('post_id') . '=' . $db->Quote($postId);

		$db->setQuery($query);
		$result = $db->loadResult() ? true : false;

		return $result;
	}


	/**
	 * Get's the total number of votes made for a specific post.
	 *
	 * @since	4.0
	 * @param	int		The unique post id.
	 * @return	int		The total number of votes.
	 *
	 */
	public function getTotalVotes($postId)
	{
		$db = $this->db;

		$query = 'SELECT SUM(' . $db->nameQuote('value') . ') AS ' . $db->nameQuote('total');
		$query .= ' FROM ' . $db->nameQuote('#__discuss_votes');
		$query .= ' WHERE ' . $db->nameQuote('post_id') . '=' . $db->Quote($postId);

		$db->setQuery($query);

		$total = $db->loadResult();

		if (is_null($total)) {
			$total = 0;
		}
		
		return $total;
	}

	/**
	 * Gets a list of voters for a particular post.
	 *
	 * @since	4.0
	 * @param	int 	The unique post id.
	 * @return	Array	An array of voter objects.
	 */
	public function getVoters($id)
	{
		$db = $this->db;
		$query 	= 'SELECT * '
				. 'FROM ' . $db->nameQuote('#__discuss_votes') . ' '
				. 'WHERE ' . $db->nameQuote('post_id') . '=' . $db->Quote($id);
		$db->setQuery($query);

		$result = $db->loadObjectList();

		return $result;
	}

	/**
	 * Resets all the votes for this particular discussion / reply.
	 *
	 * @since	4.0
	 * @access	public
	 */
	public function resetVotes($id)
	{
		$db = $this->db;

		$query = 'DELETE FROM ' . $db->nameQuote('#__discuss_votes') . ' '
			   . 'WHERE ' . $db->nameQuote('post_id') . '=' . $db->Quote($id);

		$db->setQuery($query);
		$db->Query();

	}
}
