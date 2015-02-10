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
class BLOGS_CTRL_ManagementPost extends OW_ActionController
{
    private
    $language,
    $service;

    public function __construct()
    {

        if ( !OW::getUser()->isAuthenticated() )
        {
            throw new AuthenticateException();
        }

        $this->setPageHeading(OW::getLanguage()->text('blogs', 'management_page_heading'));
        $this->setPageHeadingIconClass('ow_ic_write');

        $this->language = OW::getLanguage();
        $this->service = PostService::getInstance();

        $this->addComponent('menu', new BLOGS_CMP_ManagementMenu());
    }

    public function index()
    {
        $userId = OW::getUser()->getId();

        $page = empty($_GET['page']) ? 1 : $_GET['page'];

        $rpp = 5;


        $first = ($page - 1) * $rpp;

        $count = $rpp;

        $data = $this->getData($userId, $first, $count);

        $list = $data['list'];

        $itemCount = $data['count'];

        $pageCount = ceil($itemCount / $rpp);

        $this->assign('list', $list);
        $this->assign('status', $data['status']);

        $this->assign('thisUrl', OW_URL_HOME . OW::getRequest()->getRequestUri());

        $this->addComponent('paging', new BASE_CMP_Paging($page, $pageCount, 5));
    }

    private function getData( $userId, $first, $count )
    {
        switch ( $this->getCase() )
        {
            case 'posts':
                return array(
                    'status' => $this->language->text('blogs', 'status_published'),
                    'list' => $this->service->findUserPostList($userId, $first, $count),
                    'count' => $this->service->countUserPost($userId),
                );

            case 'drafts':
                return array(
                    'status' => $this->language->text('blogs', 'status_draft'),
                    'list' => $this->service->findUserDraftList($userId, $first, $count),
                    'count' => $this->service->countUserDraft($userId),
                );
        }

        return array();
    }

    private function getCase()
    {
        switch ( true )
        {
            case ( OW::getRouter()->getUri() == OW::getRouter()->uriForRoute('blog-manage-posts') ):
                return 'posts';

            case ( OW::getRouter()->getUri() == OW::getRouter()->uriForRoute('blog-manage-drafts') ):
                return 'drafts';
        }
    }
}