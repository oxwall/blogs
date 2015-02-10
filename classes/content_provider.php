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
 * @author Zarif Safiullin <zaph.work@gmail.com>
 * @package ow.ow_plugins.blogs
 * @since 1.7.2
 */
class BLOGS_CLASS_ContentProvider
{
    const ENTITY_TYPE = PostService::FEED_ENTITY_TYPE;

    /**
     * Singleton instance.
     *
     * @var BLOGS_CLASS_ContentProvider
     */
    private static $classInstance;

    /**
     * Returns an instance of class (singleton pattern implementation).
     *
     * @return BLOGS_CLASS_ContentProvider
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

    public function onCollectTypes( BASE_CLASS_EventCollector $event )
    {
        $event->add(array(
            "pluginKey" => "blogs",
            "group" => "blogs",
            "groupLabel" => OW::getLanguage()->text("blogs", "content_blogs_label"),
            "entityType" => self::ENTITY_TYPE,
            "entityLabel" => OW::getLanguage()->text("blogs", "content_blog_label"),
            "displayFormat" => "content"
        ));
    }

    public function onGetInfo( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params["entityType"] != self::ENTITY_TYPE )
        {
            return;
        }

        $posts = $this->service->findPostListByIds($params["entityIds"]);
        $out = array();
        /**
         * @var Post $post
         */
        foreach ( $posts as $post )
        {
            $info = array();

            $info["id"] = $post->id;
            $info["userId"] = $post->authorId;
            $info["title"] = $post->title;
            $info["description"] = $post->post;
            $info["url"] = $this->service->getPostUrl($post);
            $info["timeStamp"] = $post->timestamp;

            $out[$post->id] = $info;
        }

        $event->setData($out);

        return $out;
    }

    public function onUpdateInfo( OW_Event $event )
    {
        $params = $event->getParams();
        $data = $event->getData();

        if ( $params["entityType"] != self::ENTITY_TYPE )
        {
            return;
        }

        foreach ( $data as $postId => $info )
        {
            $status = $info["status"] == BOL_ContentService::STATUS_APPROVAL ? PostService::POST_STATUS_APPROVAL : PostService::POST_STATUS_PUBLISHED;

            $entityDto = $this->service->findById($postId);
            $entityDto->isDraft = $status;

            $this->service->save($entityDto);

            // Set tags status
            $tagActive = ($info["status"] == BOL_ContentService::STATUS_APPROVAL) ? false : true;
            BOL_TagService::getInstance()->setEntityStatus(self::ENTITY_TYPE, $postId, $tagActive);
        }
    }

    public function onDelete( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params["entityType"] != self::ENTITY_TYPE )
        {
            return;
        }

        foreach ( $params["entityIds"] as $postId )
        {
            $this->service->deletePost($postId);
        }
    }

    public function onBeforePostDelete( OW_Event $event )
    {
        $params = $event->getParams();

        OW::getEventManager()->trigger(new OW_Event(BOL_ContentService::EVENT_BEFORE_DELETE, array(
            "entityType" => self::ENTITY_TYPE,
            "entityId" => $params["postId"]
        )));
    }

    public function onAfterPostAdd( OW_Event $event )
    {
        $params = $event->getParams();

        OW::getEventManager()->trigger(new OW_Event(BOL_ContentService::EVENT_AFTER_ADD, array(
            "entityType" => self::ENTITY_TYPE,
            "entityId" => $params["postId"]
        ), array(
            "string" => array("key" => "blogs+feed_add_item_label")
        )));
    }

    public function onAfterPostEdit( OW_Event $event )
    {
        $params = $event->getParams();

        OW::getEventManager()->trigger(new OW_Event(BOL_ContentService::EVENT_AFTER_CHANGE, array(
            "entityType" => self::ENTITY_TYPE,
            "entityId" => $params["postId"]
        ), array(
            "string" => array("key" => "blogs+feed_edit_item_label")
        )));
    }

    public function init()
    {
        OW::getEventManager()->bind(PostService::EVENT_BEFORE_DELETE, array($this, "onBeforePostDelete"));
        OW::getEventManager()->bind(PostService::EVENT_AFTER_ADD, array($this, "onAfterPostAdd"));
        OW::getEventManager()->bind(PostService::EVENT_AFTER_EDIT, array($this, "onAfterPostEdit"));

        OW::getEventManager()->bind(BOL_ContentService::EVENT_COLLECT_TYPES, array($this, "onCollectTypes"));
        OW::getEventManager()->bind(BOL_ContentService::EVENT_GET_INFO, array($this, "onGetInfo"));
        OW::getEventManager()->bind(BOL_ContentService::EVENT_UPDATE_INFO, array($this, "onUpdateInfo"));
        OW::getEventManager()->bind(BOL_ContentService::EVENT_DELETE, array($this, "onDelete"));
    }
}