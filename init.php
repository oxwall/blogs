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
$plugin = OW::getPluginManager()->getPlugin('blogs');

OW::getAutoloader()->addClass('Post', $plugin->getBolDir() . 'dto' . DS . 'post.php');
OW::getAutoloader()->addClass('PostDao', $plugin->getBolDir() . 'dao' . DS . 'post_dao.php');
OW::getAutoloader()->addClass('PostService', $plugin->getBolDir() . 'service' . DS . 'post_service.php');

OW::getRouter()->addRoute(new OW_Route('blogs-uninstall', 'admin/blogs/uninstall', 'BLOGS_CTRL_Admin', 'uninstall'));

OW::getRouter()->addRoute(new OW_Route('post-save-new', 'blogs/post/new', "BLOGS_CTRL_Save", 'index'));
OW::getRouter()->addRoute(new OW_Route('post-save-edit', 'blogs/post/edit/:id', "BLOGS_CTRL_Save", 'index'));

OW::getRouter()->addRoute(new OW_Route('post', 'blogs/post/:id', "BLOGS_CTRL_View", 'index'));
OW::getRouter()->addRoute(new OW_Route('post-approve', 'blogs/post/approve/:id', "BLOGS_CTRL_View", 'approve'));

OW::getRouter()->addRoute(new OW_Route('post-part', 'blogs/post/:id/:part', "BLOGS_CTRL_View", 'index'));

OW::getRouter()->addRoute(new OW_Route('user-blog', 'blogs/user/:user', "BLOGS_CTRL_UserBlog", 'index'));

OW::getRouter()->addRoute(new OW_Route('user-post', 'blogs/:id', "BLOGS_CTRL_View", 'index'));

OW::getRouter()->addRoute(new OW_Route('blogs', 'blogs', "BLOGS_CTRL_Blog", 'index', array('list' => array(OW_Route::PARAM_OPTION_HIDDEN_VAR => 'latest'))));
OW::getRouter()->addRoute(new OW_Route('blogs.list', 'blogs/list/:list', "BLOGS_CTRL_Blog", 'index'));

OW::getRouter()->addRoute(new OW_Route('blog-manage-posts', 'blogs/my-published-posts/', "BLOGS_CTRL_ManagementPost", 'index'));
OW::getRouter()->addRoute(new OW_Route('blog-manage-drafts', 'blogs/my-drafts/', "BLOGS_CTRL_ManagementPost", 'index'));
OW::getRouter()->addRoute(new OW_Route('blog-manage-comments', 'blogs/my-incoming-comments/', "BLOGS_CTRL_ManagementComment", 'index'));

OW::getRouter()->addRoute(new OW_Route('blogs-admin', 'admin/blogs', "BLOGS_CTRL_Admin", 'index'));

$eventHandler = BLOGS_CLASS_EventHandler::getInstance();
$eventHandler->init();
BLOGS_CLASS_ContentProvider::getInstance()->init();

