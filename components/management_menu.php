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
 * @package ow_plugins.blogs.components
 * @since 1.0
 */
class BLOGS_CMP_ManagementMenu extends OW_Component
{

    public function __construct()
    {
        parent::__construct();

        $language = OW::getLanguage()->getInstance();

        $item[0] = new BASE_MenuItem();

        $item[0]->setLabel($language->text('blogs', 'manage_page_menu_published'))
            ->setOrder(0)
            ->setKey(0)
            ->setUrl(OW::getRouter()->urlForRoute('blog-manage-posts'))
            ->setActive(OW::getRequest()->getRequestUri() == OW::getRouter()->uriForRoute('blog-manage-posts'))
            ->setIconClass('ow_ic_clock');

        $item[1] = new BASE_MenuItem();

        $item[1]->setLabel($language->text('blogs', 'manage_page_menu_drafts'))
            ->setOrder(1)
            ->setKey(1)
            ->setUrl(OW::getRouter()->urlForRoute('blog-manage-drafts'))
            ->setActive(OW::getRequest()->getRequestUri() == OW::getRouter()->uriForRoute('blog-manage-drafts'))
            ->setIconClass('ow_ic_geer_wheel');

        $item[2] = new BASE_MenuItem();

        $item[2]->setLabel($language->text('blogs', 'manage_page_menu_comments'))
            ->setOrder(2)
            ->setKey(2)
            ->setUrl(OW::getRouter()->urlForRoute('blog-manage-comments'))
            ->setActive(OW::getRequest()->getRequestUri() == OW::getRouter()->uriForRoute('blog-manage-comments'))
            ->setIconClass('ow_ic_comment');

        $menu = new BASE_CMP_ContentMenu($item);

        $this->addComponent('menu', $menu);
    }
}