<?php
/**
 * DokuWiki Plugin pagemoveng (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');

class action_plugin_pagemoveng extends DokuWiki_Action_Plugin {

    var $helper = null;
    var $calls  = array('pagemoveng_popup'      => 'html_popup',
                        'pagemoveng_check_dest' => 'check_dest',
                        'pagemoveng_load_form'  => 'html_form',
                        'pagemoveng_move_page'  => 'move_page');

    function action_plugin_pagemoveng() {
        if(!$this->helper) $this->helper =& plugin_load('helper', 'pagemoveng');
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(&$controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');
        $controller->regiester_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');
    }

    function handle_ajax_call_unknown(&$event, $param) { 
        if(!in_array($event->data, array_keys($this->calls))) return;
        $event->preventDefault();
        if(auth_ismanager() or auth_isadmin()) {
            if(in_array($event->data, array_keys($this->calls))) {
                call_user_func_array(array($this->helper, $this->calls[$event->data]), array($_REQUEST['pagemove']));
            }
        }
    }

    function handle_parser_cache_use(&$event, $param) {
        // FIXME check if page is part of a move queue and fix references to moved page prior
        // FIXME should move queue be able to get fixed using an admin plugin/cli interface?
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
