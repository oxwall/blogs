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
class BLOGS_CTRL_Admin extends ADMIN_CTRL_Abstract
{

    public function __construct()
    {
        parent::__construct();

        $this->setPageHeading(OW::getLanguage()->text('blogs', 'admin_blogs_settings_heading'));
        $this->setPageHeadingIconClass('ow_ic_gear_wheel');
    }

    /**
     * Default action
     */
    public function index()
    {
        $form = new SettingsForm($this);
        if ( !empty($_POST) && $form->isValid($_POST) )
        {
            $data = $form->getValues();

            OW::getConfig()->saveConfig('blogs', 'results_per_page', $data['results_per_page']);
        }

        $this->addForm($form);
    }
    
    public function uninstall()
    {
        if ( isset($_POST['action']) && $_POST['action'] == 'delete_content' )
        {
            OW::getConfig()->saveConfig('blogs', 'uninstall_inprogress', 1);

            //maint-ce mode

            OW::getFeedback()->info(OW::getLanguage()->text('blogs', 'plugin_set_for_uninstall'));
            $this->redirect();
        }

        $this->setPageHeading(OW::getLanguage()->text('blogs', 'page_title_uninstall'));
        $this->setPageHeadingIconClass('ow_ic_delete');

        $this->assign('inprogress', (bool) OW::getConfig()->getValue('blogs', 'uninstall_inprogress'));

        $js = new UTIL_JsGenerator();

        $js->jQueryEvent('#btn-delete-content', 'click', 'if ( !confirm("'.OW::getLanguage()->text('blogs', 'confirm_delete_photos').'") ) return false;');

        OW::getDocument()->addOnloadScript($js);    	
    }    

}

class SettingsForm extends Form
{

    public function __construct( $ctrl )
    {
        parent::__construct('form');

        $configs = OW::getConfig()->getValues('blogs');

        $ctrl->assign('configs', $configs);

        $l = OW::getLanguage();

        $textField['results_per_page'] = new TextField('results_per_page');

        $textField['results_per_page']->setLabel($l->text('blogs', 'settings_results_per_page'))
            ->setValue($configs['results_per_page'])
            ->addValidator(new IntValidator())
            ->setRequired(true);

        $this->addElement($textField['results_per_page']);

        $submit = new Submit('submit');

        $submit->setValue($l->text('blogs', 'save_btn_label'));

        $this->addElement($submit);
    }
}