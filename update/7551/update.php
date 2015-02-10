<?php
/**
 * @author Zarif Safiullin <zaph.work@gmail.com>
 * @package ow.ow_plugins.pluginname
 * @since 1.7.1
 */
Updater::getLanguageService()->importPrefixFromZip(dirname(dirname(dirname(__FILE__))) . DS . 'langs.zip', 'blogs');
