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
OW::getPluginManager()->addPluginSettingsRouteName('blogs', 'blogs-admin');

OW::getPluginManager()->addUninstallRouteName('blogs', 'blogs-uninstall');

$dbPrefix = OW_DB_PREFIX;

$sql =
    <<<EOT

CREATE TABLE `{$dbPrefix}blogs_post` (
  `id` INTEGER(11) NOT NULL AUTO_INCREMENT,
  `authorId` INTEGER(11) NOT NULL,
  `title` VARCHAR(512) COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `post` TEXT COLLATE utf8_general_ci NOT NULL,
  `timestamp` INTEGER(11) NOT NULL,
  `isDraft` TINYINT(1) NOT NULL,
  `privacy` varchar(50) NOT NULL default 'everybody',
  PRIMARY KEY (`id`),
  KEY `authorId` (`authorId`)
)ENGINE=MyISAM DEFAULT CHARSET=utf8;

EOT;

OW::getDbo()->query($sql);

if ( !OW::getConfig()->configExists('blogs', 'results_per_page') )
{
    OW::getConfig()->addConfig('blogs', 'results_per_page', 10, 'Post number per page');
}

if ( !OW::getConfig()->configExists('blogs', 'uninstall_inprogress') )
{
    OW::getConfig()->addConfig('blogs', 'uninstall_inprogress', 0, '');
}

if ( !OW::getConfig()->configExists('blogs', 'uninstall_cron_busy') )
{
    OW::getConfig()->addConfig('blogs', 'uninstall_cron_busy', 0, '');
}

$authorization = OW::getAuthorization();
$groupName = 'blogs';
$authorization->addGroup($groupName);
$authorization->addAction($groupName, 'add_comment');
$authorization->addAction($groupName, 'add');
$authorization->addAction($groupName, 'view', true);

OW::getLanguage()->importPluginLangs(OW::getPluginManager()->getPlugin('blogs')->getRootDir() . 'langs.zip', 'blogs');