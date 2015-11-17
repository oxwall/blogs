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
class BLOGS_CTRL_Save extends OW_ActionController
{

    public function index( $params = array() )
    {
        if (OW::getRequest()->isAjax())
        {
            exit();
        }

        if ( !OW::getUser()->isAuthenticated() )
        {
            throw new AuthenticateException();
        }

        $plugin = OW::getPluginManager()->getPlugin('blogs');
        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'blogs', 'main_menu_item');


        $this->setPageHeading(OW::getLanguage()->text('blogs', 'save_page_heading'));
        $this->setPageHeadingIconClass('ow_ic_write');

        if ( !OW::getUser()->isAuthorized('blogs', 'add') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('blogs', 'add_blog');
            throw new AuthorizationException($status['msg']);

            return;
        }

        $this->assign('authMsg', null);

        $id = empty($params['id']) ? 0 : $params['id'];

        $service = PostService::getInstance(); /* @var $service PostService */

        $tagService = BOL_TagService::getInstance();

        if ( intval($id) > 0 )
        {
            $post = $service->findById($id);

            if ($post->authorId != OW::getUser()->getId() && !OW::getUser()->isAuthorized('blogs'))
            {
                throw new Redirect404Exception();
            }

            $eventParams = array(
                'action' => PostService::PRIVACY_ACTION_VIEW_BLOG_POSTS,
                'ownerId' => $post->authorId
            );

            $privacy = OW::getEventManager()->getInstance()->call('plugin.privacy.get_privacy', $eventParams);
            if (!empty($privacy))
            {
                $post->setPrivacy($privacy);
            }

        }
        else
        {
            $post = new Post();

            $eventParams = array(
                'action' => PostService::PRIVACY_ACTION_VIEW_BLOG_POSTS,
                'ownerId' => OW::getUser()->getId()
            );

            $privacy = OW::getEventManager()->getInstance()->call('plugin.privacy.get_privacy', $eventParams);
            if (!empty($privacy))
            {
                $post->setPrivacy($privacy);
            }

            $post->setAuthorId(OW::getUser()->getId());
        }

        $form = new SaveForm($post);

        if ( OW::getRequest()->isPost() && (!empty($_POST['command']) && in_array($_POST['command'], array('draft', 'publish')) ) && $form->isValid($_POST) )
        {
            $form->process($this);
            OW::getApplication()->redirect(OW::getRouter()->urlForRoute('post-save-edit', array('id' => $post->getId())));
        }

        $this->addForm($form);

        $this->assign('info', array('dto' => $post));

        OW::getDocument()->setTitle(OW::getLanguage()->text('blogs', 'meta_title_new_blog_post'));
        OW::getDocument()->setDescription(OW::getLanguage()->text('blogs', 'meta_description_new_blog_post'));

    }

    public function delete( $params )
    {
        if (OW::getRequest()->isAjax() || !OW::getUser()->isAuthenticated())
        {
            exit();
        }
        /*
          @var $service PostService
         */
        $service = PostService::getInstance();

        $id = $params['id'];

        $dto = $service->findById($id);

        if ( !empty($dto) )
        {
            if ($dto->authorId == OW::getUser()->getId() || OW::getUser()->isAuthorized('blogs'))
            {
                OW::getEventManager()->trigger(new OW_Event(PostService::EVENT_BEFORE_DELETE, array(
                    'postId' => $id
                )));
                $service->delete($dto);
                OW::getEventManager()->trigger(new OW_Event(PostService::EVENT_AFTER_DELETE, array(
                    'postId' => $id
                )));
            }
        }

        if ( !empty($_GET['back-to']) )
        {
            $this->redirect($_GET['back-to']);
        }

        $author = BOL_UserService::getInstance()->findUserById($dto->authorId);

        $this->redirect(OW::getRouter()->urlForRoute('user-blog', array('user' => $author->getUsername())));
    }
}

class SaveForm extends Form
{
    /**
     *
     * @var Post
     */
    private $post;
    /**
     *
     * @var type PostService
     */
    private $service;


    public function __construct( Post $post, $tags = array() )
    {
        parent::__construct('save');

        $this->service = PostService::getInstance();

        $this->post = $post;

        $this->setMethod('post');

        $titleTextField = new TextField('title');

        $this->addElement($titleTextField->setLabel(OW::getLanguage()->text('blogs', 'save_form_lbl_title'))->setValue($post->getTitle())->setRequired(true));

        $buttons = array(
            BOL_TextFormatService::WS_BTN_BOLD,
            BOL_TextFormatService::WS_BTN_ITALIC,
            BOL_TextFormatService::WS_BTN_UNDERLINE,
            BOL_TextFormatService::WS_BTN_IMAGE,
            BOL_TextFormatService::WS_BTN_LINK,
            BOL_TextFormatService::WS_BTN_ORDERED_LIST,
            BOL_TextFormatService::WS_BTN_UNORDERED_LIST,
            BOL_TextFormatService::WS_BTN_MORE,
            BOL_TextFormatService::WS_BTN_SWITCH_HTML,
            BOL_TextFormatService::WS_BTN_HTML,
            BOL_TextFormatService::WS_BTN_VIDEO
        );

        $postTextArea = new WysiwygTextarea('post', $buttons);
        $postTextArea->setSize(WysiwygTextarea::SIZE_L);
        $postTextArea->setLabel(OW::getLanguage()->text('blogs', 'save_form_lbl_post'));
        $postTextArea->setValue($post->getPost());
        $postTextArea->setRequired(true);
        $this->addElement($postTextArea);

        $draftSubmit = new Submit('draft');
        $draftSubmit->addAttribute('onclick', "$('#save_post_command').attr('value', 'draft');");

        if ( $post->getId() != null && !$post->isDraft() )
        {
            $text = OW::getLanguage()->text('blogs', 'change_status_draft');
        }
        else
        {
            $text = OW::getLanguage()->text('blogs', 'sava_draft');
        }

        $this->addElement($draftSubmit->setValue($text));

        if ( $post->getId() != null && !$post->isDraft() )
        {
            $text = OW::getLanguage()->text('blogs', 'update');
        }
        else
        {
            $text = OW::getLanguage()->text('blogs', 'save_publish');
        }

        $publishSubmit = new Submit('publish');
        $publishSubmit->addAttribute('onclick', "$('#save_post_command').attr('value', 'publish');");

        $this->addElement($publishSubmit->setValue($text));

        $tagService = BOL_TagService::getInstance();

        $tags = array();

        if ( intval($this->post->getId()) > 0 )
        {
            $arr = $tagService->findEntityTags($this->post->getId(), 'blog-post');

            foreach ( (!empty($arr) ? $arr : array() ) as $dto )
            {
                $tags[] = $dto->getLabel();
            }
        }

        $tf = new TagsInputField('tf');
        $tf->setLabel(OW::getLanguage()->text('blogs', 'tags_field_label'));
        $tf->setValue($tags);

        $this->addElement($tf);
    }

    public function process( $ctrl )
    {
        OW::getCacheManager()->clean( array( PostDao::CACHE_TAG_POST_COUNT ));

        $service = PostService::getInstance(); /* @var $postDao PostService */

        $data = $this->getValues();

        $data['title'] = UTIL_HtmlTag::stripJs($data['title']);

        $postIsNotPublished = $this->post->getStatus() == 2;

        $text = UTIL_HtmlTag::sanitize($data['post']);

        /* @var $post Post */
        $this->post->setTitle($data['title']);
        $this->post->setPost($text);
        $this->post->setIsDraft($_POST['command'] == 'draft');

        $isCreate = empty($this->post->id);
        if ( $isCreate )
        {
            $this->post->setTimestamp(time());
            //Required to make #698 and #822 work together
            if ( $_POST['command'] == 'draft' )
            {
                $this->post->setIsDraft(2);
            }

        }
        else
        {
            //If post is not new and saved as draft, remove their item from newsfeed
            if ( $_POST['command'] == 'draft' )
            {
                OW::getEventManager()->trigger(new OW_Event('feed.delete_item', array('entityType' => 'blog-post', 'entityId' => $this->post->id)));
            }
            else if($postIsNotPublished)
            {
                // Update timestamp if post was published for the first time
                $this->post->setTimestamp(time());
            }

        }

        $service->save($this->post);

        $tags = array();
        if ( intval($this->post->getId()) > 0 )
        {
            $tags = $data['tf'];
            foreach ( $tags as $id => $tag )
            {
                $tags[$id] = UTIL_HtmlTag::stripTags($tag);
            }
        }
        $tagService = BOL_TagService::getInstance();
        $tagService->updateEntityTags($this->post->getId(), 'blog-post', $tags );

        if ( $this->post->isDraft() )
        {
            $tagService->setEntityStatus('blog-post', $this->post->getId(), false);

            if ( $isCreate )
            {
                OW::getFeedback()->info(OW::getLanguage()->text('blogs', 'create_draft_success_msg'));
            }
            else
            {
                OW::getFeedback()->info(OW::getLanguage()->text('blogs', 'edit_draft_success_msg'));
            }
        }
        else
        {
            $tagService->setEntityStatus('blog-post', $this->post->getId(), true);

            //Newsfeed
            $event = new OW_Event('feed.action', array(
                'pluginKey' => 'blogs',
                'entityType' => 'blog-post',
                'entityId' => $this->post->getId(),
                'userId' => $this->post->getAuthorId(),
            ));
            OW::getEventManager()->trigger($event);

            if ( $isCreate )
            {
                OW::getFeedback()->info(OW::getLanguage()->text('blogs', 'create_success_msg'));

                OW::getEventManager()->trigger(new OW_Event(PostService::EVENT_AFTER_ADD, array(
                    'postId' => $this->post->getId()
                )));
            }
            else
            {
                OW::getFeedback()->info(OW::getLanguage()->text('blogs', 'edit_success_msg'));
                OW::getEventManager()->trigger(new OW_Event(PostService::EVENT_AFTER_EDIT, array(
                    'postId' => $this->post->getId()
                )));
            }

            $blog_post = PostService::getInstance()->findById($this->post->id);

            if( $blog_post->isDraft == PostService::POST_STATUS_PUBLISHED )
            {
                BOL_AuthorizationService::getInstance()->trackActionForUser($blog_post->authorId, 'blogs', 'add_blog');
            }

            $ctrl->redirect(OW::getRouter()->urlForRoute('post', array('id' => $this->post->getId())));
        }
    }
}

?>
