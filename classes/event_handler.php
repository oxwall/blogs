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
 * @package ow.ow_plugins.blogs.classes
 * @since 1.6.0
 */
class BLOGS_CLASS_EventHandler
{
    /**
     * Singleton instance.
     *
     * @var BLOGS_CLASS_EventHandler
     */
    private static $classInstance;

    /**
     * Returns an instance of class (singleton pattern implementation).
     *
     * @return BLOGS_CLASS_EventHandler
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }
    /**
     *
     * @var PostService
     */
    private $service;

    private function __construct()
    {
        $this->service = PostService::getInstance();
    }

    public function genericInit()
    {
        OW::getEventManager()->bind(OW_EventManager::ON_USER_SUSPEND, array(PostService::getInstance(), 'onAuthorSuspend'));

        OW::getEventManager()->bind(OW_EventManager::ON_USER_UNREGISTER, array($this, 'onUnregisterUser'));
        OW::getEventManager()->bind('notifications.collect_actions', array($this, 'onCollectNotificationActions'));
        OW::getEventManager()->bind('base_add_comment', array($this, 'onAddBlogPostComment'));
        //OW::getEventManager()->bind('base_delete_comment',                array($this, 'onDeleteComment'));
        OW::getEventManager()->bind('ads.enabled_plugins', array($this, 'onCollectEnabledAdsPages'));

        OW::getEventManager()->bind('admin.add_auth_labels', array($this, 'onCollectAuthLabels'));
        OW::getEventManager()->bind('feed.collect_configurable_activity', array($this, 'onCollectFeedConfigurableActivity'));
        OW::getEventManager()->bind('feed.collect_privacy', array($this, 'onCollectFeedPrivacyActions'));
        OW::getEventManager()->bind('plugin.privacy.get_action_list', array($this, 'onCollectPrivacyActionList'));
        OW::getEventManager()->bind('plugin.privacy.on_change_action_privacy', array($this, 'onChangeActionPrivacy'));

        OW::getEventManager()->bind('feed.on_entity_add', array($this, 'onAddBlogPost'));
        OW::getEventManager()->bind('feed.on_entity_update', array($this, 'onUpdateBlogPost'));
        OW::getEventManager()->bind('feed.after_comment_add', array($this, 'onFeedAddComment'));
        OW::getEventManager()->bind('feed.after_like_added', array($this, 'onFeedAddLike'));

        OW::getEventManager()->bind('socialsharing.get_entity_info', array($this, 'sosialSharingGetBlogInfo'));

        $credits = new BLOGS_CLASS_Credits();
        OW::getEventManager()->bind('usercredits.on_action_collect', array($credits, 'bindCreditActionsCollect'));
        OW::getEventManager()->bind('usercredits.get_action_key', array($credits, 'getActionKey'));
    }

    public function onCollectAddNewContentItem( BASE_CLASS_EventCollector $event )
    {
        $resultArray = array(
            BASE_CMP_AddNewContent::DATA_KEY_ICON_CLASS => 'ow_ic_write',
            BASE_CMP_AddNewContent::DATA_KEY_LABEL => OW::getLanguage()->text('blogs', 'add_new_link'),
            BASE_CMP_AddNewContent::DATA_KEY_ID => 'addNewBlogPostBtn'
        );

        if ( OW::getUser()->isAuthenticated() && OW::getUser()->isAuthorized('blogs', 'add') )
        {
            $resultArray[BASE_CMP_AddNewContent::DATA_KEY_URL] = OW::getRouter()->urlForRoute('post-save-new');

            $event->add($resultArray);
        }
        else
        {
            $resultArray[BASE_CMP_AddNewContent::DATA_KEY_URL] = 'javascript://';

            $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'add');

            if ( $status['status'] == BOL_AuthorizationService::STATUS_PROMOTED )
            {
                $script = '$("#addNewBlogPostBtn").click(function(){
                    OW.authorizationLimitedFloatbox('.json_encode($status['msg']).');
                });';
                OW::getDocument()->addOnloadScript($script);

                $event->add($resultArray);
            }
        }
    }

    public function onCollectNotificationActions( BASE_CLASS_EventCollector $e )
    {
        $e->add(array(
            'section' => 'blogs',
            'action' => 'blogs-add_comment',
            'description' => OW::getLanguage()->text('blogs', 'email_notifications_setting_comment'),
            'selected' => true,
            'sectionLabel' => OW::getLanguage()->text('blogs', 'notification_section_label'),
            'sectionIcon' => 'ow_ic_write'
        ));
    }

    public function onAddBlogPostComment( OW_Event $event )
    {
        $params = $event->getParams();

        if ( empty($params['entityType']) || $params['entityType'] !== 'blog-post' )
            return;

        $entityId = $params['entityId'];
        $userId = $params['userId'];
        $commentId = $params['commentId'];

        $postService = PostService::getInstance();

        $post = $postService->findById($entityId);


        if ( $userId == $post->authorId )
        {
            return;
        }

        $actor = array(
            'name' => BOL_UserService::getInstance()->getDisplayName($userId),
            'url' => BOL_UserService::getInstance()->getUserUrl($userId)
        );

        $comment = BOL_CommentService::getInstance()->findComment($commentId);

        $avatars = BOL_AvatarService::getInstance()->getDataForUserAvatars(array($userId));

        $event = new OW_Event('notifications.add', array(
            'pluginKey' => 'blogs',
            'entityType' => 'blogs-add_comment',
            'entityId' => (int) $comment->getId(),
            'action' => 'blogs-add_comment',
            'userId' => $post->authorId,
            'time' => time()
        ), array(
            'avatar' => $avatars[$userId],
            'string' => array(
                'key' => 'blogs+comment_notification_string',
                'vars' => array(
                    'actor' => $actor['name'],
                    'actorUrl' => $actor['url'],
                    'title' => $post->getTitle(),
                    'url' => OW::getRouter()->urlForRoute('post', array('id' => $post->getId()))
                )
            ),
            'content' => $comment->getMessage(),
            'url' => OW::getRouter()->urlForRoute('post', array('id' => $post->getId()))
        ));

        OW::getEventManager()->trigger($event);
    }

    public function onDeleteComment( OW_Event $event )
    {
        $params = $event->getParams();

        if ( empty($params['entityType']) || $params['entityType'] !== 'blog-post' )
            return;

        $entityId = $params['entityId'];
        $userId = $params['userId'];
        $commentId = (int) $params['commentId'];
    }

    public function onUnregisterUser( OW_Event $event )
    {
        $params = $event->getParams();

        if ( empty($params['deleteContent']) )
        {
            return;
        }

        OW::getCacheManager()->clean(array(PostDao::CACHE_TAG_POST_COUNT));

        $userId = $params['userId'];

        $count = (int) $this->service->countUserPost($userId);

        if ( $count == 0 )
        {
            return;
        }

        $list = $this->service->findUserPostList($userId, 0, $count);

        foreach ( $list as $post )
        {
            $this->service->delete($post);
        }
    }

    public function onCollectEnabledAdsPages( BASE_CLASS_EventCollector $event )
    {
        $event->add('blogs');
    }

    public function onCollectAuthLabels( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();
        $event->add(
            array(
                'blogs' => array(
                    'label' => $language->text('blogs', 'auth_group_label'),
                    'actions' => array(
                        'add' => $language->text('blogs', 'auth_action_label_add'),
                        'view' => $language->text('blogs', 'auth_action_label_view'),
                        'add_comment' => $language->text('blogs', 'auth_action_label_add_comment')
                    )
                )
            )
        );
    }

    public function onCollectFeedConfigurableActivity( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();
        $event->add(array(
            'label' => $language->text('blogs', 'feed_content_label'),
            'activity' => '*:blog-post'
        ));
    }

    public function onCollectFeedPrivacyActions( BASE_CLASS_EventCollector $event )
    {
        $event->add(array('*:blog-post', PostService::PRIVACY_ACTION_VIEW_BLOG_POSTS));
    }

    public function onCollectPrivacyActionList( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();

        $action = array(
            'key' => PostService::PRIVACY_ACTION_VIEW_BLOG_POSTS,
            'pluginKey' => 'blogs',
            'label' => $language->text('blogs', 'privacy_action_view_blog_posts'),
            'description' => '',
            'defaultValue' => 'everybody'
        );

        $event->add($action);

        $action = array(
            'key' => PostService::PRIVACY_ACTION_COMMENT_BLOG_POSTS,
            'pluginKey' => 'blogs',
            'label' => $language->text('blogs', 'privacy_action_comment_blog_posts'),
            'description' => '',
            'defaultValue' => 'everybody'
        );

        $event->add($action);
    }

    public function onChangeActionPrivacy( OW_Event $event )
    {
        $params = $event->getParams();

        $userId = (int) $params['userId'];
        $actionList = $params['actionList'];
        $actionList = is_array($actionList) ? $actionList : array();

        if ( empty($actionList[PostService::PRIVACY_ACTION_VIEW_BLOG_POSTS]) )
        {
            return;
        }

        PostService::getInstance()->updateBlogsPrivacy($userId, $actionList[PostService::PRIVACY_ACTION_VIEW_BLOG_POSTS]);
    }

    public function onCollectQuickLinks( BASE_CLASS_EventCollector $event )
    {
        $userId = OW::getUser()->getId();
        $username = OW::getUser()->getUserObject()->getUsername();

        $postCount = (int) $this->service->countUserPost($userId);
        $draftCount = (int) $this->service->countUserDraft($userId);
        $count = $postCount + $draftCount;
        if ( $count > 0 )
        {
            if ( $postCount > 0 )
            {
                $url = OW::getRouter()->urlForRoute('blog-manage-posts');
            }
            else if ( $draftCount > 0 )
            {
                $url = OW::getRouter()->urlForRoute('blog-manage-drafts');
            }

            $event->add(array(
                BASE_CMP_QuickLinksWidget::DATA_KEY_LABEL => OW::getLanguage()->text('blogs', 'my_blog'),
                BASE_CMP_QuickLinksWidget::DATA_KEY_URL => OW::getRouter()->urlForRoute('user-blog', array('user' => $username)),
                BASE_CMP_QuickLinksWidget::DATA_KEY_COUNT => $count,
                BASE_CMP_QuickLinksWidget::DATA_KEY_COUNT_URL => $url,
            ));
        }
    }

    public function onAddBlogPost( OW_Event $e )
    {
        $params = $e->getParams();
        $data = $e->getData();

        if ( $params['entityType'] != 'blog-post' )
        {
            return;
        }

        $post = $this->service->findById($params['entityId']);

        $content = nl2br(UTIL_String::truncate(strip_tags($post->post), 150, '...'));
        $title = UTIL_String::truncate(strip_tags($post->title), 100, '...');

        $data = array(
            'time' => (int) $post->timestamp,
            'ownerId' => $post->authorId,
            'string'=>array("key" => "blogs+feed_add_item_label"),
            'content' => array(
                'format' => 'content',
                'vars' => array(
                    'title' => $title,
                    'description' => $content,
                    'url' => array(
                        "routeName" => 'post',
                        "vars" => array('id' => $post->id)
                    ),
                    'iconClass' => 'ow_ic_blog'
                )
            ),
            'view' => array(
                'iconClass' => 'ow_ic_write'
            )
        );

        $e->setData($data);
    }

    public function onUpdateBlogPost( OW_Event $e )
    {
        $params = $e->getParams();
        $data = $e->getData();

        if ( $params['entityType'] != 'blog-post' )
        {
            return;
        }

        $post = $this->service->findById($params['entityId']);

        $content = nl2br(UTIL_String::truncate(strip_tags($post->post), 150, '...'));
        $title = UTIL_String::truncate(strip_tags($post->title), 100, '...');

        $data = array(
            'time' => (int) $post->timestamp,
            'ownerId' => $post->authorId,
            'string'=>array("key" => "blogs+feed_add_item_label"),
            'content' => array(
                'format' => 'content',
                'vars' => array(
                    'title' => $title,
                    'description' => $content,
                    'url' => array(
                        "routeName" => 'post',
                        "vars" => array('id' => $post->id)
                    ),
                    'iconClass' => 'ow_ic_blog'
                )
            ),
            'view' => array(
                'iconClass' => 'ow_ic_write'
            ),
            'actionDto' => $data['actionDto']
        );

        $e->setData($data);
    }

    public function onFeedAddComment( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params['entityType'] != 'blog-post' )
        {
            return;
        }

        $post = $this->service->findById($params['entityId']);
        $userId = $post->getAuthorId();

        $userName = BOL_UserService::getInstance()->getDisplayName($userId);
        $userUrl = BOL_UserService::getInstance()->getUserUrl($userId);
        $userEmbed = '<a href="' . $userUrl . '">' . $userName . '</a>';

        if ( $userId == $params['userId'] )
        {
            $string = array(
                'key'=>'blogs+feed_activity_owner_post_string'
            );
        }
        else
        {
            $string = array(
                'key'=>'blogs+feed_activity_post_string',
                'vars'=>array('user' => $userEmbed)
            );
        }

        OW::getEventManager()->trigger(new OW_Event('feed.activity', array(
                'activityType' => 'comment',
                'activityId' => $params['commentId'],
                'entityId' => $params['entityId'],
                'entityType' => $params['entityType'],
                'userId' => $params['userId'],
                'pluginKey' => 'blogs'
                ), array(
                'string' => $string
            )));
    }

    public function onFeedAddLike( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params['entityType'] != 'blog-post' )
        {
            return;
        }

        $post = $this->service->findById($params['entityId']);
        $userId = $post->getAuthorId();

        $userName = BOL_UserService::getInstance()->getDisplayName($userId);
        $userUrl = BOL_UserService::getInstance()->getUserUrl($userId);
        $userEmbed = '<a href="' . $userUrl . '">' . $userName . '</a>';

        if ( $userId == $params['userId'] )
        {
            $string = array(
                'key'=>'blogs+feed_activity_owner_post_string_like'
            );
        }
        else
        {
            $string = array(
                'key'=>'blogs+feed_activity_post_string_like',
                'vars'=>array('user' => $userEmbed)
            );
        }

        OW::getEventManager()->trigger(new OW_Event('feed.activity', array(
                'activityType' => 'like',
                'activityId' => $params['userId'],
                'entityId' => $params['entityId'],
                'entityType' => $params['entityType'],
                'userId' => $params['userId'],
                'pluginKey' => 'blogs'
                ), array(
                'string' => $string
            )));
    }

    public function sosialSharingGetBlogInfo( OW_Event $event )
    {
        $params = $event->getParams();
        $data = $event->getData();
        $data['display'] = false;

        if ( empty($params['entityId']) )
        {
            return;
        }

        if ( $params['entityType'] == 'user_blog' )
        {
            if( BOL_AuthorizationService::getInstance()->isActionAuthorizedForGuest('blogs', 'view') )
            {
                $data['display'] = true;
            }

            $event->setData($data);
            return;
        }

        if ( $params['entityType'] == 'blogs' )
        {
            $blogtDto = PostService::getInstance()->findById($params['entityId']);

            $displaySocialSharing = true;

            try
            {
                $eventParams = array(
                    'action' => 'blogs_view_blog_posts',
                    'ownerId' => $blogtDto->getAuthorId(),
                    'viewerId' => 0
                );

                OW::getEventManager()->getInstance()->call('privacy_check_permission', $eventParams);
            }
            catch ( RedirectException $ex )
            {
                $displaySocialSharing = false;
            }


            if ( $displaySocialSharing && ( !BOL_AuthorizationService::getInstance()->isActionAuthorizedForGuest('blogs', 'view') || $blogtDto->isDraft() ) )
            {
                $displaySocialSharing = false;
            }

            if ( !empty($blogtDto) )
            {
                $data['display'] = $displaySocialSharing;
            }

            $event->setData($data);
        }
    }
}