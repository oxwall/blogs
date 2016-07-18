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
class BLOGS_CTRL_View extends OW_ActionController
{

    public function index( $params )
    {

        $username = !empty($params['user']) ? $params['user'] : '';

        $id = $params['id'];

        $plugin = OW::getPluginManager()->getPlugin('blogs');

        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'blogs', 'main_menu_item');

        $service = PostService::getInstance();

        $userService = BOL_UserService::getInstance();

        $this->assign('user', ((OW::getUser()->getId() !== null) ? $userService->findUserById(OW::getUser()->getId()) : null));

        $post = $service->findById($id);

        if ( $post === null )
        {
            throw new Redirect404Exception();
        }

        if ($post->isDraft() && $post->authorId != OW::getUser()->getId())
        {
            throw new Redirect404Exception();
        }

        $post->post = BASE_CMP_TextFormatter::fromBBtoHtml($post->post);
        $post->setTitle( strip_tags($post->getTitle()) );

        if ( !OW::getUser()->isAuthorized('blogs', 'view') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'view');
            throw new AuthorizationException($status['msg']);

            return;
        }

        if ( ( OW::getUser()->isAuthenticated() && OW::getUser()->getId() != $post->getAuthorId() ) && !OW::getUser()->isAuthorized('blogs', 'view') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'view');
            throw new AuthorizationException($status['msg']);

            return;
        }

        /* Check privacy permissions */
        if ( $post->authorId != OW::getUser()->getId() && !OW::getUser()->isAuthorized('blogs') )
        {
            $eventParams = array(
                'action' => 'blogs_view_blog_posts',
                'ownerId' => $post->authorId,
                'viewerId' => OW::getUser()->getId()
            );

            OW::getEventManager()->getInstance()->call('privacy_check_permission', $eventParams);
        }
        /* */

        $parts = explode('<!--page-->', $post->getPost());

        $page = !empty($_GET['page']) ? $_GET['page'] : 1;

        $count = count($parts);

        if ( strlen($username) > 0 )
        {
            $author = $userService->findByUsername($username);
        }
        else
        {
            $author = $userService->findUserById($post->getAuthorId());
            $isAuthorExists = !empty($author);
            if ( $isAuthorExists )
            {
                $username = $author->getUsername();
            }
        }

        $this->assign('isAuthorExists', $isAuthorExists);

        if ( $isAuthorExists )
        {
            $displayName = $userService->getDisplayName($author->getId());

            $this->assign('username', $userService->getUserName($author->getId()));
            $this->assign('displayname', $displayName);

            $url = OW::getRouter()->urlForRoute('user-blog', array('user' => $username));

            $pending_approval_text = '';
            if ($post->getStatus() == PostService::POST_STATUS_APPROVAL)
            {
                $pending_approval_text = '<span class="ow_remark ow_small">('.OW::getLanguage()->text('base', 'pending_approval').')</span>';
            }
            $this->setPageHeading(OW::getLanguage()->text('blogs', 'view_page_heading', array('url' => $url, 'name' => $displayName, 'postTitle' => htmlspecialchars($post->getTitle()))) .' '. $pending_approval_text );
            $this->setPageHeadingIconClass('ow_ic_write');

            OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'blog_post_title', array('post_title' => htmlspecialchars($post->getTitle()), 'display_name' => $displayName)));

            $post_body = UTIL_String::truncate($post->getPost(), 200, '...');
            $postTagsArray = BOL_TagService::getInstance()->findEntityTags($post->getId(), 'blog-post');
            $postTags = "";

            foreach ( $postTagsArray as $tag )
            {
                $postTags .= $tag->label . ", ";
            }
            $postTags = substr($postTags, 0, -2);
            //OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'blog_post_description', array('post_body' => htmlspecialchars(strip_tags($post_body)), 'tags' => htmlspecialchars($postTags))));
            //OW::getDocument()->setKeywords(OW::getLanguage()->text('nav', 'page_default_keywords').", ".$postTags);
        }



        $info = array(
            'dto' => $post,
            'text' => $parts[$page - 1]
        );

        $this->assign('info', $info);

        if ( $isAuthorExists )
        {
            //blog navigation
            $prev = $service->findAdjacentUserPost($author->getId(), $post->getId(), 'prev');
            $next = $service->findAdjacentUserPost($author->getId(), $post->getId(), 'next');

            if ( !empty($prev) )
            {
                $prevUser = $userService->findUserById($prev->getAuthorId());
            }

            if ( !empty($next) )
            {
                $nextUser = $userService->findUserById($next->getAuthorId());
            }

            $this->assign('adjasentUrl',
                array(
                    'next' => (!empty($nextUser) ) ? OW::getRouter()->urlForRoute('user-post', array('id' => $next->getId(), 'user' => $nextUser->getUsername())) : '',
                    'prev' => (!empty($prevUser) ) ? OW::getRouter()->urlForRoute('user-post', array('id' => $prev->getId(), 'user' => $prevUser->getUsername())) : '',
                    'index' => OW::getRouter()->urlForRoute('user-blog', array('user' => $author->getUsername()))
                )
            );
        }
        else
        {
            $this->assign('adjasentUrl', null);
        }
        //~blog navigation
        //toolbar

        $tb = array();

        $toolbarEvent = new BASE_CLASS_EventCollector('blogs.collect_post_toolbar_items', array(
            'postId' => $post->id,
            'postDto' => $post
        ));

        OW::getEventManager()->trigger($toolbarEvent);

        foreach ( $toolbarEvent->getData() as $toolbarItem )
        {
            array_push($tb, $toolbarItem);
        }

        if ($post->getStatus() == PostService::POST_STATUS_APPROVAL && OW::getUser()->isAuthorized('blogs'))
        {
            $tb[] = array(
                'label' => OW::getLanguage()->text('base', 'approve'),
                'href' => OW::getRouter()->urlForRoute('post-approve', array('id'=>$post->getId())),
                'id' => 'blog_post_toolbar_approve',
                'class'=>'ow_mild_green'
            );
        }

        if ( OW::getUser()->isAuthenticated() && ( $post->getAuthorId() != OW::getUser()->getId() ) )
        {
            $js = UTIL_JsGenerator::newInstance()
                ->jQueryEvent('#blog_post_toolbar_flag', 'click', UTIL_JsGenerator::composeJsString('OW.flagContent({$entityType}, {$entityId});',
                            array(
                        'entityType' => PostService::FEED_ENTITY_TYPE,
                        'entityId' => $post->getId()
            )));

            OW::getDocument()->addOnloadScript($js, 1001);

            $tb[] = array(
                'label' => OW::getLanguage()->text('base', 'flag'),
                'href' => 'javascript://',
                'id' => 'blog_post_toolbar_flag'
            );
        }
        if ( OW::getUser()->isAuthenticated() && ( OW::getUser()->getId() == $post->getAuthorId() || OW::getUser()->isAuthorized('blogs') ) )
        {
            $tb[] = array(
                'href' => OW::getRouter()->urlForRoute('post-save-edit', array('id' => $post->getId())),
                'label' => OW::getLanguage()->text('blogs', 'toolbar_edit')
            );

            $tb[] = array(
                'href' => OW::getRouter()->urlFor('BLOGS_CTRL_Save', 'delete', array('id' => $post->getId())),
                'click' => "return confirm('" . OW::getLanguage()->text('base', 'are_you_sure') . "');",
                'label' => OW::getLanguage()->text('blogs', 'toolbar_delete')
            );
        }

        $this->assign('tb', $tb);
        //~toolbar

        $paging = new BASE_CMP_Paging($page, $count, $count);

        //<ARCHIVE-NAVIGATOR>


        $this->assign('paging', $paging->render());
        if ( $isAuthorExists )
        {
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
        }

        //</ARCHIVE-NAVIGATOR>
        if ( $isAuthorExists )
        {
            $this->assign('author', $author);
        }

        $this->assign('isModerator', OW::getUser()->isAuthorized('blogs'));
        if ( $isAuthorExists )
        {
            $this->assign('userBlogUrl', OW::getRouter()->urlForRoute('user-blog', array('user' => $author->getUsername())));
        }

        $rateInfo = new BASE_CMP_Rate('blogs', 'blog-post', $post->getId(), $post->getAuthorId());

        /* Check comments privacy permissions */
        $allow_comments = true;
        if ($post->getStatus() == PostService::POST_STATUS_APPROVAL)
        {
            $allow_comments = false;
            $rateInfo->setVisible(false);
        }
        else
        {
            if ( $post->authorId != OW::getUser()->getId() && !OW::getUser()->isAuthorized('blogs') )
            {
                $eventParams = array(
                    'action' => 'blogs_comment_blog_posts',
                    'ownerId' => $post->authorId,
                    'viewerId' => OW::getUser()->getId()
                );

                try
                {
                    OW::getEventManager()->getInstance()->call('privacy_check_permission', $eventParams);
                }
                catch ( RedirectException $ex )
                {
                    $allow_comments = false;
                }
            }
        }
        /* */

        $this->addComponent('rate', $rateInfo);

        // additional components
        $cmpParams = new BASE_CommentsParams('blogs', 'blog-post');
        $cmpParams->setEntityId($post->getId())
            ->setOwnerId($post->getAuthorId())
            ->setDisplayType(BASE_CommentsParams::DISPLAY_TYPE_BOTTOM_FORM_WITH_FULL_LIST)
            ->setAddComment($allow_comments);

        $this->addComponent('comments', new BASE_CMP_Comments($cmpParams));

        $this->assign('avatarUrl', '');

        $tagCloud = new BASE_CMP_EntityTagCloud('blog-post', OW::getRouter()->urlForRoute('blogs.list', array('list'=>'browse-by-tag')));

        $tagCloud->setEntityId($post->getId());

        $this->addComponent('tagCloud', $tagCloud);
        //~ additional components

        $eParams = array(
            "sectionKey" => "blogs",
            "entityKey" => "blogPost",
            "title" => "blogs+meta_title_blog_post",
            "description" => "blogs+meta_desc_blog_post",
            "keywords" => "blogs+meta_keywords_blog_post",
            "vars" => array("post_body" => htmlspecialchars(strip_tags($post_body)), "post_subject" => $post->getTitle())
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $eParams));
    }

    public function approve($params)
    {
        if (!OW::getUser()->isAuthenticated())
        {
            throw new AuthenticateException();
        }

        if (!OW::getUser()->isAuthorized('blogs'))
        {
            throw new Redirect403Exception();
        }

        //TODO trigger event for content moderation;
        $postId = $params['id'];
        $postDto = PostService::getInstance()->findById($postId);
        if (!$postDto)
        {
            throw new Redirect404Exception();
        }

        $backUrl = OW::getRouter()->urlForRoute('post', array('id'=>$postId));

        $event = new OW_Event("moderation.approve", array(
            "entityType" => PostService::FEED_ENTITY_TYPE,
            "entityId" => $postId
        ));

        OW::getEventManager()->trigger($event);

        $data = $event->getData();
        if ( empty($data) )
        {
            $this->redirect($backUrl);
        }

        if ( $data["message"] )
        {
            OW::getFeedback()->info($data["message"]);
        }
        else
        {
            OW::getFeedback()->error($data["error"]);
        }

        $this->redirect($backUrl);
    }
}