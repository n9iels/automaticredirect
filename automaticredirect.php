<?php
/**
 * @package     Automatic Redirect
 *
 * @copyright   Copyright (C) 2018 Niels van der Veer. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

JLoader::register('ContentHelperRoute', JPATH_ROOT . '/components/com_content/helpers/route.php');

/**
 * Plug-in to create a redirect for an article when it's alias is changed
 *
 * @since  1.0
 */

class PlgContentAutomaticRedirect extends JPlugin
{
	/**
	 * Hook in before the article is save to check if the alias is changed. If yes, create a redirct
	 *
	 * @param   string   $context  The context of the content passed to the plugin
	 * @param   object   $article  A JTableContent object.
	 * @param   boolean  $isNew    A boolean that indicates if the article is new
	 *
	 * @return  mixed   true if there is an error. Void otherwise.
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeSave($context, $article, $isNew, $data)
	{
		if ($context != 'com_content.article' || $isNew)
		{
			return true;
		}
		
		$originalArticle = $this->getArticle($data['id']);

		if ($data['alias'] === $originalArticle->alias)
		{
			return true;
		}
		
		$app    = JApplication::getInstance('site');
		$router = $app->getRouter();

		// Generate the old en new article url en create redirects
		$oldUrl = str_replace('/administrator', '', $router->build(ContentHelperRoute::getArticleRoute($originalArticle->id, $originalArticle->catid, $originalArticle->language)));
		$newUrl = str_replace($originalArticle->alias, $data['alias'], $oldUrl);
		
		$this->deleteRedirect($oldUrl);
		$this->updateRedirect($oldUrl, 'new_url', $newUrl);
		$this->createRedirect($oldUrl, $newUrl);
		$this->cleanUselessRedirect();

		return true;
	}

	/**
	 * Receive the original article form the database
	 *
	 * @param   integer  $articleid  ID of the article to receive
	 *
	 * @return  mixed    stdClass, null otherwise
	 *
	 * @since   1.0.0
	 */
	private function getArticle($articleId)
	{
		$db = \Joomla\CMS\Factory::getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__content'))
			->where($db->qn('id') . ' = ' . $db->q($articleId));
		$db->setQuery($query);

		return $db->loadObject();
	}

	/**
	 * Add a 301 redirect
	 * 
	 * @param   string  $oldUrl  The old url to redirect
	 * @param   string  $newUrl  The new url
	 */
	private function createRedirect($oldUrl, $newUrl)
	{
		$date  = \Joomla\CMS\Factory::getDate();
		$db    = \Joomla\CMS\Factory::getDbo();
		$query = $db->getQuery(true);

		$columns = array('old_url', 'new_url', 'published', 'header', 'created_date', 'modified_date');
		$values  = array($db->quote($oldUrl), $db->quote($newUrl), 1, 301, $db->quote($date->toSql()), $db->quote($date->toSql()));

		$query
			->insert($db->quoteName('#__redirect_links'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));

		// Set the query using our newly populated query object and execute it.
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Search for all redirect by 'new_url' and update it to a new value
	 * 
	 * @param   string  $urlToUpdate  Search parameter for all new urls
	 * @param   string  $urlType      The kind of url to update. This can be 'new_url' or 'old_url'
	 * @param   string  $newUrl       The new url
	 */
	private function updateRedirect($urlToUpdate, $urlType, $newUrl)
	{
		$db      = \Joomla\CMS\Factory::getDbo();
		$query   = $db->getQuery(true);

		$fields = array($db->quoteName($urlType) . ' = ' . $db->quote($newUrl),);
		$conditions = array($db->quoteName($urlType) . ' = ' . $db->quote($urlToUpdate));

		$query
			->update($db->quoteName('#__redirect_links'))
			->set($fields)
			->where($conditions);

		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Remove a redirect
	 * 
	 * @param   string  $url  The url to remove
	 */
	private function deleteRedirect($url)
	{
		$db         = \Joomla\CMS\Factory::getDbo();
		$query      = $db->getQuery(true);
		$conditions = array(
			$db->quoteName('old_url') . ' = ' . $db->quote($url)
		);

		// Prepare the insert query.
		$query
			->delete($db->quoteName('#__redirect_links'))
			->where($conditions);

		// Set the query using our newly populated query object and execute it.
		$db->setQuery($query);
		$db->execute();
	}

	/**
	 * Delete redirects where the new_url is the same as the old_url
	 */
	private function cleanUselessRedirect()
	{
		$db         = \Joomla\CMS\Factory::getDbo();
		$query      = $db->getQuery(true);
		$conditions = array(
			$db->quoteName('old_url') . ' = ' . $db->quoteName('new_url')
		);

		// Prepare the insert query.
		$query
			->delete($db->quoteName('#__redirect_links'))
			->where($conditions);

		// Set the query using our newly populated query object and execute it.
		$db->setQuery($query);
		$db->execute();
	}
}
?>
