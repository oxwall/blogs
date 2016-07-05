<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * @author Zarif Safiullin <zaph.saph@gmail.com>
 * @package ow_plugins.blogs.controllers
 * @since 1.0
 */
class BLOGS_CTRL_Blog extends OW_ActionController
{

    public function index($params)
    {
        if ( empty($params['list']) )
        {
            $params['list'] = 'latest';
        }

        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'blogs', 'main_menu_item');

        $this->setPageHeading(OW::getLanguage()->text('blogs', 'list_page_heading'));
        $this->setPageHeadingIconClass('ow_ic_write');

        if ( !OW::getUser()->isAdmin() && !OW::getUser()->isAuthorized('blogs', 'view') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'view');
            throw new AuthorizationException($status['msg']);

            return;
        }

        $page = (!empty($_GET['page']) && intval($_GET['page']) > 0 ) ? $_GET['page'] : 1;

        $addNew_promoted = false;
        $addNew_isAuthorized = false;
        if (OW::getUser()->isAuthenticated())
        {
            if (OW::getUser()->isAuthorized('blogs', 'add'))
            {
                $addNew_isAuthorized = true;
            }
            else
            {
                $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'add');
                if ($status['status'] == BOL_AuthorizationService::STATUS_PROMOTED)
                {
                    $addNew_promoted = true;
                    $addNew_isAuthorized = true;
                    $script = '$("#btn-add-new-post").click(function(){
                        OW.authorizationLimitedFloatbox('.json_encode($status['msg']).');
                        return false;
                    });';
                    OW::getDocument()->addOnloadScript($script);
                }
                else
                {
                    $addNew_isAuthorized = false;
                }
            }
        }

        $this->assign('addNew_isAuthorized', $addNew_isAuthorized);
        $this->assign('addNew_promoted', $addNew_promoted);

        $rpp = (int) OW::getConfig()->getValue('blogs', 'results_per_page');

        $first = ($page - 1) * $rpp;

        $count = $rpp;

        $case = $params['list'];
        if ( !in_array($case, array( 'latest', 'browse-by-tag', 'most-discussed', 'top-rated' )) )
        {
            throw new Redirect404Exception();
        }
        $showList = true;
        $isBrowseByTagCase = $case == 'browse-by-tag';

        $contentMenu = $this->getContentMenu();
        $contentMenu->getElement($case)->setActive(true);
        $this->addComponent('menu', $contentMenu );
        $this->assign('listType', $case);

        $this->assign('isBrowseByTagCase', $isBrowseByTagCase);

        $tagSearch = new BASE_CMP_TagSearch(OW::getRouter()->urlForRoute('blogs.list', array('list'=>'browse-by-tag')));

        $this->addComponent('tagSearch', $tagSearch);

        $tagCloud = new BASE_CMP_EntityTagCloud('blog-post', OW::getRouter()->urlForRoute('blogs.list', array('list'=>'browse-by-tag')));

        if ( $isBrowseByTagCase )
        {
            $tagCloud->setTemplate(OW::getPluginManager()->getPlugin('base')->getCmpViewDir() . 'big_tag_cloud.html');

            $tag = !(empty($_GET['tag'])) ? UTIL_HtmlTag::stripTags($_GET['tag']) : '';
            $this->assign('tag', $tag );

            if (empty($tag))
            {
                $showList = false;
            }
        }

        $this->addComponent('tagCloud', $tagCloud);
        $this->assign('showList', $showList);

        list($list, $itemsCount) = $this->getData($case, $first, $count);

        $posts = array();
        $authorIdList = array();

        foreach ( $list as $item )
        {
            $dto = $item['dto'];

            $dto->setPost($dto->getPost());
            $dto->setTitle( UTIL_String::truncate( strip_tags($dto->getTitle()), 65, '...' )  );

            $text = explode("<!--more-->", $dto->getPost());

            $isPreview = count($text) > 1;

            if ( !$isPreview )
            {
                $text = explode('<!--page-->', $text[0]);
                $showMore = count($text) > 1;
            }
            else
            {
                $showMore = true;
            }

            $text = $text[0];

            $posts[] = array(
                'dto' => $dto,
                'text' => $text,
                'showMore' => $showMore,
                'url' => OW::getRouter()->urlForRoute('user-post', array('id'=>$dto->getId()))
            );

            $authorIdList[] = $dto->authorId;
            $idList[] = $dto->getId();
        }

        if ( !empty($idList) )
        {
            $avatars = BOL_AvatarService::getInstance()->getDataForUserAvatars($authorIdList, true, false);
            $this->assign('avatars', $avatars);

            $nlist = array();
            foreach ( $avatars as $userId => $avatar )
            {
                $nlist[$userId] = $avatar['title'];
            }
            $urls = BOL_UserService::getInstance()->getUserUrlsForList($authorIdList);
            $this->assign('toolbars', $this->getToolbar($idList, $list, $urls, $nlist));
        }

        $this->assign('list', $posts);
        $this->assign('url_new_post', OW::getRouter()->urlForRoute('post-save-new'));

        $paging = new BASE_CMP_Paging($page, ceil($itemsCount / $rpp), 5);
        $this->addComponent('paging', $paging);

        $params = array(
            "sectionKey" => "blogs",
            "entityKey" => "blogsList",
            "title" => "blogs+meta_title_blogs_list",
            "description" => "blogs+meta_desc_blogs_list",
            "keywords" => "blogs+meta_keywords_blogs_list",
            "vars" => array( "blog_list" => OW::getLanguage()->text("blogs", str_replace("-", "_", $case)."_title") )
        );
        
        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
    }

    private function getData( $case, $first, $count )
    {
        $service = PostService::getInstance();

        $list = array();
        $itemsCount = 0;

        switch ( $case )
        {
            case 'most-discussed':

//                OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'most_discussed_title'));
//                OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'most_discussed_description'));

                $commentService = BOL_CommentService::getInstance();

                $info = array();

                $info = $commentService->findMostCommentedEntityList('blog-post', $first, $count);

                $idList = array();

                foreach ( $info as $item )
                {
                    $idList[] = $item['id'];
                }

                if ( empty($idList) )
                {
                    break;
                }

                $dtoList = $service->findListByIdList($idList);

                foreach ( $dtoList as $dto )
                {
                    if ($dto->isDraft())
                    {
                        continue;
                    }
                    $info[$dto->id]['dto'] = $dto;

                    $list[] = array(
                        'dto' => $dto,
                        'commentCount' => $info[$dto->id] ['commentCount'],
                    );
                }

                function sortMostCommented( $e, $e2 )
                {

                    return $e['commentCount'] < $e2['commentCount'];
                }
                usort($list, 'sortMostCommented');

                $itemsCount = $commentService->findCommentedEntityCount('blog-post');

                break;

            case 'top-rated':

//                OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'top_rated_title'));
//                OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'top_rated_description'));

                $info = array();

                $info = BOL_RateService::getInstance()->findMostRatedEntityList('blog-post', $first, $count);

                $idList = array();

                foreach ( $info as $item )
                {
                    $idList[] = $item['id'];
                }

                if ( empty($idList) )
                {
                    break;
                }

                $dtoList = $service->findListByIdList($idList);

                foreach ( $dtoList as $dto )
                {
                    if ($dto->isDraft())
                    {
                        continue;
                    }
                    $list[] = array(
                        'dto' => $dto,
                        'avgScore' => $info[$dto->id] ['avgScore'],
                        'ratesCount' => $info[$dto->id] ['ratesCount']
                    );
                }

                function sortTopRated( $e, $e2 )
                {
                    if ($e['avgScore'] == $e2['avgScore'])
                    {
                        if ($e['ratesCount'] == $e2['ratesCount'])
                        {
                            return 0;
                        }

                        return $e['ratesCount'] < $e2['ratesCount'];
                    }
                    return $e['avgScore'] < $e2['avgScore'];
                }
                usort($list, 'sortTopRated');

                $itemsCount = BOL_RateService::getInstance()->findMostRatedEntityCount('blog-post');

                break;

            case 'browse-by-tag':
                if ( empty($_GET['tag']) )
                {
                    $mostPopularTagsArray = BOL_TagService::getInstance()->findMostPopularTags('blog-post', 20);
                    $mostPopularTags = "";

                    foreach ( $mostPopularTagsArray as $tag )
                    {
                        $mostPopularTags .= $tag['label'] . ", ";
                    }

//                    OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'browse_by_tag_title'));
//                    OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'browse_by_tag_description', array('tags' => $mostPopularTags)));

                    break;
                }

                $info = BOL_TagService::getInstance()->findEntityListByTag('blog-post', UTIL_HtmlTag::stripTags($_GET['tag']), $first, $count);

                $itemsCount = BOL_TagService::getInstance()->findEntityCountByTag('blog-post', UTIL_HtmlTag::stripTags($_GET['tag']));

                foreach ( $info as $item )
                {
                    $idList[] = $item;
                }

                if ( empty($idList) )
                {
                    break;
                }

                $dtoList = $service->findListByIdList($idList);

                function sortByTimestamp( $post1, $post2 )
                {
                    return $post1->timestamp < $post2->timestamp;
                }
                usort($dtoList, 'sortByTimestamp');


                foreach ( $dtoList as $dto )
                {
                    if ($dto->isDraft())
                    {
                        continue;
                    }
                    $list[] = array('dto' => $dto);
                }

//                OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'browse_by_tag_item_title', array('tag' => UTIL_HtmlTag::stripTags($_GET['tag']))));
//                OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'browse_by_tag_item_description', array('tag' => UTIL_HtmlTag::stripTags($_GET['tag']))));

                break;

            case 'latest':
//                OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'latest_title'));
//                OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'latest_description'));

                $arr = $service->findList($first, $count);

                foreach ( $arr as $item )
                {
                    $list[] = array('dto' => $item);
                }

                $itemsCount = $service->countPosts();

                break;
        }

        return array($list, $itemsCount);
    }

    /**
     * Get top menu for Blog post list
     *
     * @return BASE_CMP_ContentMenu
     */
    private function getContentMenu()
    {
        $menuItems = array();

        $listNames = array(
            'browse-by-tag' => array('iconClass' => 'ow_ic_tag'),
            'most-discussed' => array('iconClass' => 'ow_ic_comment'),
            'top-rated' => array('iconClass' => 'ow_ic_star'),
            'latest' => array('iconClass' => 'ow_ic_clock')
        );

        foreach ( $listNames as $listKey => $listArr )
        {
            $menuItem = new BASE_MenuItem();
            $menuItem->setKey($listKey);
            $menuItem->setUrl(OW::getRouter()->urlForRoute('blogs.list', array('list' => $listKey)));
            $menuItemKey = explode('-', $listKey);
            $listKey = "";
            foreach ($menuItemKey as $key)
            {
                $listKey .= strtoupper(substr($key, 0, 1)).substr($key, 1);
            }

            $menuItem->setLabel(OW::getLanguage()->text('blogs', 'menuItem'.$listKey));
            $menuItem->setIconClass($listArr['iconClass']);
            $menuItems[] = $menuItem;
        }

        return new BASE_CMP_ContentMenu($menuItems);
    }

    private function getToolbar( $idList, $list, $ulist, $nlist )
    {
        if ( empty($idList) )
        {
            return array();
        }

        $info = array();

        $info['comment'] = BOL_CommentService::getInstance()->findCommentCountForEntityList('blog-post', $idList);

        $info['rate'] = BOL_RateService::getInstance()->findRateInfoForEntityList('blog-post', $idList);

        $info['tag'] = BOL_TagService::getInstance()->findTagListByEntityIdList('blog-post', $idList);

        $toolbars = array();

        foreach ( $list as $item )
        {
            $id = $item['dto']->id;

            $userId = $item['dto']->authorId;

            $toolbars[$id] = array(
                array(
                    'class' => 'ow_icon_control ow_ic_user',
                    'label' => !empty($nlist[$userId]) ? $nlist[$userId] : OW::getLanguage()->text('base', 'deleted_user'),
                    'href' => !empty($ulist[$userId]) ? $ulist[$userId] : '#'
                ),
                array(
                    'class' => 'ow_ipc_date',
                    'label' => UTIL_DateTime::formatDate($item['dto']->timestamp)
                ),
            );

            if ( $info['rate'][$id]['avg_score'] > 0 )
            {
                $toolbars[$id][] = array(
                    'label' => OW::getLanguage()->text('blogs', 'rate') . ' <span class="ow_txt_value">' . ( ( $info['rate'][$id]['avg_score'] - intval($info['rate'][$id]['avg_score']) == 0 ) ? intval($info['rate'][$id]['avg_score']) : sprintf('%.2f', $info['rate'][$id]['avg_score']) ) . '</span>',
                );
            }

            if ( !empty($info['comment'][$id]) )
            {
                $toolbars[$id][] = array(
                    'label' => OW::getLanguage()->text('blogs', 'comments') . ' <span class="ow_txt_value">' . $info['comment'][$id] . '</span>',
                );
            }


            if ( empty($info['tag'][$id]) )
            {
                continue;
            }

            $value = "<span class='ow_wrap_normal'>" . OW::getLanguage()->text('blogs', 'tags') . ' ';

            foreach ( $info['tag'][$id] as $tag )
            {
                $value .='<a href="' . OW::getRouter()->urlForRoute('blogs.list', array('list'=>'browse-by-tag')) . "?tag={$tag}" . "\">{$tag}</a>, ";
            }

            $value = mb_substr($value, 0, mb_strlen($value) - 2);
            $value .= "</span>";
            $toolbars[$id][] = array(
                'label' => $value,
            );
        }

        return $toolbars;
    }
}