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
class BLOGS_CTRL_UserBlog extends OW_ActionController
{

    public function index( $params )
    {
        $plugin = OW::getPluginManager()->getPlugin('blogs');

        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'blogs', 'main_menu_item');

        if ( !OW::getUser()->isAdmin() && !OW::getUser()->isAuthorized('blogs', 'view') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'view');
            throw new AuthorizationException($status['msg']);

            return;
        }

        /*
          @var $service PostService
         */
        $service = PostService::getInstance();

        /*
          @var $userService BOL_UserService
         */
        $userService = BOL_UserService::getInstance();

        /*
          @var $author BOL_User
         */
        if ( !empty($params['user']) )
        {
            $author = $userService->findByUsername($params['user']);
        }
        else
        {
            $author = $userService->findUserById(OW::getUser()->getId());
        }

        if ( empty($author) )
        {
            throw new Redirect404Exception();
            return;
        }

        /* Check privacy permissions */
        $eventParams = array(
            'action' => 'blogs_view_blog_posts',
            'ownerId' => $author->getId(),
            'viewerId' => OW::getUser()->getId()
        );

        OW::getEventManager()->getInstance()->call('privacy_check_permission', $eventParams);
        /* */
        
        
        $displaySocialSharing = true;
        
        try {
            $eventParams = array(
                'action' => 'blogs_view_blog_posts',
                'ownerId' => $author->getId(),
                'viewerId' => 0
            );

            OW::getEventManager()->getInstance()->call('privacy_check_permission', $eventParams);
        }
        catch( RedirectException $ex )
        {
            $displaySocialSharing  = false;
        }

        
        if ( $displaySocialSharing && !BOL_AuthorizationService::getInstance()->isActionAuthorizedForUser(0, 'blogs', 'view')  )
        {
            $displaySocialSharing  = false;
        }
        
        $this->assign('display_social_sharing', $displaySocialSharing);

        $displayName = $userService->getDisplayName($author->getId());

        $this->assign('author', $author);
        $this->assign('username', $author->getUsername());
        $this->assign('displayname', $displayName);

        $this->setPageHeading(OW::getLanguage()->text('blogs', 'user_blog_page_heading', array('name' => $author->getUsername())));
        $this->setPageHeadingIconClass('ow_ic_write');

        $page = (!empty($_GET['page']) && intval($_GET['page']) > 0 ) ? intval($_GET['page']) : 1;

        $rpp = (int) OW::getConfig()->getValue('blogs', 'results_per_page');

        $first = ($page - 1) * $rpp;
        $count = $rpp;

        if ( !empty($_GET['month']) )
        {
            $archive_params = htmlspecialchars($_GET['month']);
            $arr = explode('-', $archive_params);
            $month = $arr[0];
            $year = $arr[1];

            $lb = mktime(null, null, null, $month, 1, $year);
            $ub = mktime(null, null, null, $month + 1, null, $year);

            $list = $service->findUserPostListByPeriod($author->getId(), $lb, $ub, $first, $count);

            $itemsCount = $service->countUserPostByPeriod($author->getId(), $lb, $ub);

            $l = OW::getLanguage();
            $arciveHeaderPart = ', ' . $l->text('base', "month_{$month}") . " {$year} " . $l->text('base', 'archive');

            OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'user_blog_archive_title', array('month_name'=>$l->text('base', "month_{$month}"), 'display_name'=>$displayName)));
            OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'user_blog_archive_description', array('year'=>$year, 'month_name'=>$l->text('base', "month_{$month}"), 'display_name'=>$displayName) ));
        }
        else
        {
            $list = $service->findUserPostList($author->getId(), $first, $count);

            $itemsCount = $service->countUserPost($author->getId());

            // meta info
            $vars = BOL_SeoService::getInstance()->getUserMetaInfo($author);

            $eParams = array(
                "sectionKey" => "blogs",
                "entityKey" => "userBlog",
                "title" => "blogs+meta_title_user_blog",
                "description" => "blogs+meta_desc_user_blog",
                "keywords" => "blogs+meta_keywords_user_blog",
                "vars" => $vars
            );

            OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $eParams));

//            OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'user_blog_title', array('display_name'=>$displayName)));
//            OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'user_blog_description', array('display_name'=>$displayName) ));
        }

        $this->assign('archiveHeaderPart', (!empty($arciveHeaderPart) ? $arciveHeaderPart : ''));

        $posts = array();

        $commentInfo = array();

        $idList = array();

        foreach ( $list as $dto ) /* @var dto Post */
        {
            $idList[] = $dto->getId();
            $dto_post = BASE_CMP_TextFormatter::fromBBtoHtml($dto->getPost());

            $dto->setPost($dto_post);
            $parts = explode('<!--more-->', $dto->getPost());

            if (!empty($parts))
            {
                $text = $parts[0];
                //$text = UTIL_HtmlTag::sanitize($text);
            }
            else
            {
                $text = $dto->getPost();
            }

            $posts[] = array(
                'id' => $dto->getId(),
                'href' => OW::getRouter()->urlForRoute('user-post', array('id' => $dto->getId())),
                'title' => UTIL_String::truncate($dto->getTitle(), 65, '...'),
                'text' => $text,
                'truncated' => (count($parts) > 1) ? true: false,
            );
        }

        if ( !empty($idList) )
        {
            $commentInfo = BOL_CommentService::getInstance()->findCommentCountForEntityList('blog-post', $idList);
            $this->assign('commentInfo', $commentInfo);

            $tagsInfo = BOL_TagService::getInstance()->findTagListByEntityIdList('blog-post', $idList);
            $this->assign('tagsInfo', $tagsInfo);

            $tb = array();

            foreach ( $list as $dto ) /* @var dto Post */
            {

                $tb[$dto->getId()] = array(
                    array(
                        'label' => UTIL_DateTime::formatDate($dto->timestamp)
                    ),
                );

                //if ( $commentInfo[$dto->getId()] )
                //{
                    $tb[$dto->getId()][] = array(
                        'href' => OW::getRouter()->urlForRoute('post', array('id' => $dto->getId())),
                        'label' => '<span class="ow_outline">' . $commentInfo[$dto->getId()] . '</span> ' . OW::getLanguage()->text('blogs', 'toolbar_comments')
                    );
                //}

                if ( $tagsInfo[$dto->getId()] )
                {
                    $tags = &$tagsInfo[$dto->getId()];
                    $t = OW::getLanguage()->text('blogs', 'tags');
                    for ( $i = 0; $i < (count($tags) > 3 ? 3 : count($tags)); $i++ )
                    {
                        $t .= " <a href=\"" . OW::getRouter()->urlForRoute('blogs.list', array('list'=>'browse-by-tag')) . "?tag={$tags[$i]}\">{$tags[$i]}</a>" . ( $i != 2 ? ',' : '' );
                    }

                    $tb[$dto->getId()][] = array('label' => mb_substr($t, 0, mb_strlen($t) - 1));
                }
            }

            $this->assign('tb', $tb);
        }

        $this->assign('list', $posts);

        $info = array(
            'lastPost' => $service->findUserLastPost($author->getId()),
            'author' => $author,
        );

        $this->assign('info', $info);

        $paging = new BASE_CMP_Paging($page, ceil($itemsCount / $rpp), 5);

        $this->assign('paging', $paging->render());

        $rows = $service->findUserArchiveData($author->getId());

        $archive = array();

        foreach ( $rows as $row )
        {
            if ( !array_key_exists($row['y'], $archive) )
            {
                $archive[$row['y']] = array();
            }

            $archive[$row['y']][] = $row['m'];
        }
        $this->assign('archive', $archive);

        $this->assign('my_drafts_url', OW::getRouter()->urlForRoute('blog-manage-drafts'));

        if (OW::getUser()->isAuthenticated())
        {
        $isOwner = ( $params['user'] == OW::getUser()->getUserObject()->getUsername() ) ? true : false;
        }
        else
        {
            $isOwner = false;
        }

        $this->assign('isOwner', $isOwner);
    }
}
