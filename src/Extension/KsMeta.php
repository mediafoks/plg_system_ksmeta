<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.KsMeta
 *
 * @copyright   (C) 2024 Sergey Kuznetsov. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\KsMeta\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\Component\Content\Administrator\Extension\ContentComponent;

/**
 * Ks Meta plugin
 *
 * @since  1.0
 */
class KsMeta extends CMSPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation
     *
     * @var    boolean
     * @since  1.0
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => 'onBeforeCompileHead',
        ];
    }

    public function renderMeta($params): void
    {
        $app = $this->getApplication();
        $doc = $app->getDocument();
        $head = $doc->getHeadData();

        empty($params->get('title_prefix')) ?: $head['title'] = $params->get('title_prefix') . $head['title'];
        empty($params->get('title_suffix')) ?: $head['title'] = $head['title'] . $params->get('title_suffix');
        empty($params->get('description_prefix')) ?: $head['description'] = $params->get('description_prefix') . $head['description'];
        empty($params->get('description_prefix')) ?: $head['description'] = $head['description'] . $params->get('description_suffix');

        $doc->setHeadData($head);
    }

    /**
     * Plugin method for the 'onContentSomeEvent' event.
     *
     * @param   string  $context  The context of the event
     * @param   mixed   $data     The data related to the event
     *
     * @return  void
     *
     * @since   1.0
     */
    public function onBeforeCompileHead(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) return; // если это не фронтэнд, то прекращаем работу

        $factory = $app->bootComponent('com_content')->getMVCFactory();
        $input = $app->getInput();
        $appParams = $app->getParams();
        $view = $app->input->get('view');
        $option = $app->input->get('option');

        // echo '<pre>';
        // \var_dump($view);
        // echo '</pre>';

        if ($view === 'article') {
            $articles_params = $this->params->get('articles'); // параметры материалов
            $articles = $factory->createModel('Articles', 'Site', ['ignore_request' => true]);
            $articles->setState('params', $appParams);
            $articles->setState('list.start', 0);
            $articles->setState('filter.published', ContentComponent::CONDITION_PUBLISHED);

            foreach ($articles_params as $params) {
                $params = new Registry($params);
                $catids = $params->get('catid');

                if ($catids) {
                    if ($params->get('show_child_category_articles', 0) && (int) $params->get('levels', 0) > 0) {
                        $categories = $factory->createModel('Categories', 'Site', ['ignore_request' => true]);
                        $categories->setState('params', $appParams);
                        $levels = $params->get('levels', 1) ?: 9999;
                        $categories->setState('filter.get_children', $levels);
                        $categories->setState('filter.published', 1);
                        $additional_catids = [];

                        foreach ($catids as $catid) {
                            $categories->setState('filter.parentId', $catid);
                            $recursive = true;
                            $items = $categories->getItems($recursive);

                            if ($items) {
                                foreach ($items as $category) {
                                    $condition = (($category->level - $categories->getParent()->level) <= $levels);
                                    if ($condition) {
                                        $additional_catids[] = $category->id;
                                    }
                                }
                            }
                        }

                        $catids = array_unique(array_merge($catids, $additional_catids));
                    }

                    $articles->setState('filter.category_id', $catids);

                    $ex_or_include_articles = $params->get('ex_or_include_articles', 0);
                    $filterInclude = true;
                    $articlesList = [];
                    $currentArticleId = $input->get('id', 0, 'UINT');

                    $articlesListToProcess = $params->get('included_articles', '');

                    if ($ex_or_include_articles === 0) {
                        $filterInclude = false;
                        $articlesListToProcess = $params->get('excluded_articles', '');
                    }

                    foreach (ArrayHelper::fromObject($articlesListToProcess) as $article) {
                        $articlesList[] = (int) $article['id'];
                    }

                    if ($ex_or_include_articles === 1 && empty($articlesList)) {
                        $filterInclude  = false;
                        $articlesList[] = $currentArticleId;
                    }

                    if (!empty($articlesList)) {
                        $articles->setState('filter.article_id', $articlesList);
                        $articles->setState('filter.article_id.include', $filterInclude);
                    }

                    $items = $articles->getItems();

                    foreach ($items as $item) {
                        if ($item->id === $currentArticleId) {
                            $this->renderMeta($params);
                            break;
                        }
                    }
                }
            }
        } elseif ($view === 'category' && $option === 'com_content') {
            $categories_params = $this->params->get('categories'); // параметры категорий

            foreach ($categories_params as $params) {
                $params = new Registry($params);
                $catids = $params->get('catid');

                if ($catids) {
                    $categories = $factory->createModel('Categories', 'Site', ['ignore_request' => true]);
                    $categories->setState('params', $appParams);
                    $categories->setState('extension', 'com_content');

                    if ($params->get('show_child_category_articles', 0) && (int) $params->get('levels', 0) > 0) {
                        $levels = $params->get('levels', 1) ?: 9999;
                        $categories->setState('filter.get_children', $levels);
                        $categories->setState('filter.published', 1);
                        $additional_catids = [];

                        foreach ($catids as $catid) {
                            $categories->setState('filter.parentId', $catid);
                            $recursive = true;
                            $items = $categories->getItems($recursive);

                            if ($items) {
                                foreach ($items as $category) {
                                    $condition = (($category->level - $categories->getParent()->level) <= $levels);
                                    if ($condition) {
                                        $additional_catids[] = $category->id;
                                    }
                                }
                            }
                        }

                        $catids = array_unique(array_merge($catids, $additional_catids));
                    }

                    $ex_or_include_categories = $params->get('ex_or_include_categories', 0);
                    $filterInclude = true;
                    $categoriesList = [];
                    $currentCategoryId = $input->get('id', 0, 'UINT');

                    $categoriesListToProcess = $params->get('included_categories', '');

                    if ($ex_or_include_categories === 0) {
                        $filterInclude = false;
                        $categoriesListToProcess = $params->get('excluded_categories', '');
                    }

                    foreach (ArrayHelper::fromObject($categoriesListToProcess) as $category) {
                        $categoriesList[] = (int) $category['id'];
                    }

                    if (!empty($categoriesList)) {
                        $ex_or_include_categories === 1 ? $catids = $categoriesList : $catids = array_diff($catids, $categoriesList);
                    }

                    if ($ex_or_include_categories === 1 && empty($categoriesList)) {
                        $filterInclude  = false;
                        $categoriesList[] = $currentCategoryId;
                    }

                    foreach ($catids as $catid) {
                        if ($catid === $currentCategoryId) {
                            $this->renderMeta($params);
                            break;
                        }
                    }
                }
            }
        } elseif ($view === 'tag') {
            $tags_params = $this->params->get('tags'); // параметры тегов

            foreach ($tags_params as $params) {
                $params = new Registry($params);
                $tagids = $params->get('tag');

                if ($tagids) {
                    $tag = $app->bootComponent('com_tags')->getMVCFactory()->createModel('Tag', 'Site', ['ignore_request' => true]);
                    $currentTagId = $input->get('id', 0, 'UINT')[0];
                    $currentTagParentId = $tag->getItem((int) $currentTagId)[0]->get('parent_id');
                    $excluded_tags = $params->get('excluded_tag');

                    if (!empty($excluded_tags)) {
                        foreach ($excluded_tags as $excluded_tag) {
                            if ($currentTagId !== $excluded_tag) {
                                if (in_array($currentTagId, $tagids) || in_array($currentTagParentId, $tagids)) {
                                    $this->renderMeta($params);
                                }
                            }
                        }
                    } else {
                        if (in_array($currentTagId, $tagids) || in_array($currentTagParentId, $tagids)) {
                            $this->renderMeta($params);
                        }
                    }
                }
            }
        } elseif ($view === 'contact') {
            $contacts_params = $this->params->get('contacts'); // параметры контактов

            foreach ($contacts_params as $params) {
                $params = new Registry($params);
                $catid = $params->get('catid');

                if ($catid) {
                    $contact = $app->bootComponent('com_contact')->getMVCFactory()->createModel('Contact', 'Site', ['ignore_request' => false]);
                    $currentContactId = (int) $input->get('id');
                    $currentContactCatId = $contact->getItem($currentContactId)->catid;
                    $excluded_contacts = $params->get('excluded_contacts');

                    if (!empty($excluded_contacts)) {
                        foreach ($excluded_contacts as $excluded_contact) {
                            $excludedContactId = (int) $excluded_contact->id;

                            if ($currentContactId !== $excludedContactId) {
                                if (in_array($currentContactCatId, $catid)) {
                                    $this->renderMeta($params);
                                }
                            }
                        }
                    } else {
                        if (in_array($currentContactCatId, $catid)) {
                            $this->renderMeta($params);
                        }
                    }
                }
            }
        }
    }
}
