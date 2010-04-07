<?php
/**
 * DokuWiki Plugin pagemoveng (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Klier <chi@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class helper_plugin_pagemoveng extends DokuWiki_Plugin {

    var $queue_fn = null;

    function helper_plugin_pagemoveng() {
        $this->queue_fn = metaFN('plugin_pagemoveng', '.queue');
    }

    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function tpl_button() {
        global $ID;
        if(!auth_isadmin() or !auth_ismanager() or !page_exists($ID)) return;
        $form = new Doku_Form(array('id' => 'plugin__pagemoveng_btn'));
        $form->addElement(formSecurityToken());
        $form->addHidden('pagemove[id]', $ID);
        //$form->addElement('<span id="plugin__pagemoveng_id">' . $ID . '</span>');
        $form->addElement('<div id="plugin__pagemoveng_wrapper"><div id="plugin__pagemoveng_popup"></div></div>');
        $form->addElement(form_makeButton('button', '', $this->getLang('btn_move'), array('id' => 'plugin__pagemoveng_btn_popup')));
        html_form('pagemoveng_btn', $form);
    }

    function tpl_actionlink() {
        // FIXME
        if(!auth_isadmin() or !auth_ismanager() or !page_exists($ID)) return;
    }

    function html_popup($argv) {
        global $lang;
        if(!auth_isadmin() or !auth_ismanager()) return;

        // display page move form if we're inside the top-level namespace
        if(getNS($argv['id']) == '') {
            $this->html_form(array('mode' => 'page', 'id' => $argv['id']));
        } else {
            print $this->locale_xhtml('dialog');
            $form = new Doku_Form(array('id' => 'plugin__pagemoveng_dialog'));
            $form->addElement(formSecurityToken());
            $form->addElement(form_makeButton('submit', '', $this->getLang('btn_move_page'), array('id' => 'plugin__pagemoveng_btn_move_page')));
            $form->addElement(form_makeButton('submit', '', $this->getLang('btn_move_ns'), array('id' => 'plugin__pagemoveng_btn_move_ns')));
            $form->addElement(form_makeButton('submit', '', $lang['btn_cancel'], array('id' => 'plugin__pagemoveng_btn_cancel')));
            html_form('pagemoveng_popup', $form); 
        }
    }

    function html_form($argv) {
        global $lang;
        global $ID;

        if(!auth_isadmin() or !auth_ismanager()) return;

        print $this->locale_xhtml('form');
        print '<div id="plugin__pagemoveng_dialog">' . DOKU_LF;

        $form = new Doku_Form(array('id' => 'plugin__pagemoveng_form'));
        $form->startFieldset($this->getLang('form_legend'));

        $form->addHidden('pagemove[id]', $argv['id']);
        $form->addHidden('pagemove[mode]', $argv['mode']);

        $info = $this->collect_info($argv);
        $form->addElement('<div id="plugin__pagemoveng_info">' . $this->pinfo($info) . '</div>');

        foreach($info['pages'] as $page) {
            $form->addElement(form_makeTextField('pagemove[pages][]', $page, null, null, 'hidden'));
        }

        $form->addElement(form_makeTextField('pagemove[dest]', '', $this->getLang('label_dest'), 'plugin__pagemoveng_dest'));

        // FIXME ALT TEXT
        $form->addElement(form_makeCheckboxField('pagemove[action][history]', 1, 
                          $this->getLang('label_history'), 'plugin__pagemoveng_act_history', '', array('checked' => 'checked')));

        $form->addElement(form_makeCheckboxField('pagemove[action][meta]', 1, 
                          $this->getLang('label_meta'), 'plugin__pagemoveng_act_meta', '', array('checked' => 'checked')));

        $form->addElement(form_makeCheckboxField('pagemove[action][media]', 1, 
                          $this->getLang('label_media'), 'plugin__pagemoveng_act_media', '', array('checked' => 'checked')));

        $form->addElement(form_makeCheckboxField('pagemove[opt][overwrite]', 1, 
                          $this->getLang('label_overwrite'), 'plugin__pagemoveng_opt_overwrite'));

        $form->addElement(form_makeCheckboxField('pagemove[opt][ignorelock]', 1, 
                          $this->getLang('label_ignorelock'), 'plugin__pagemoveng_opt_ignorelock'));

        // FIXME other options?

        $form->addElement(form_makeButton('submit', 'move_page', $this->getLang('btn_move'), array('id' => 'plugin__pagemoveng_btn_move')));
        $form->addElement(form_makeButton('submit', '', $lang['btn_cancel'], array('id' => 'plugin__pagemoveng_btn_cancel')));

        $form->endFieldset();
        $form->addElement('<div id="plugin__pagemoveng_progress"><div id="plugin__pagemoveng_progressbar"></div></div>');
        html_form('pagemoveng_popup', $form); 

        print '</div>' . DOKU_LF;
    }

    function collect_info($argv) {
        global $conf;
        $info = array();

        if($argv['mode'] == 'page') {
            $info['backlinks'][] = ft_backlinks($argv['id']);
            $info['meta'][]      = metaFiles($argv['id']);
            $info['pages'][]     = $argv['id'];
        } else {
            $info['ns']    = getNS($argv['id']);
            $info['pages'] = array();

            $data = array();
            search($data, $conf['datadir'], 'search_allpages', 
                   array('skipacl' => 0), utf8_encodeFN(str_replace(':', '/', $info['ns'])));

            foreach($data as $item) {
                array_push($info['pages'], $item['id']);
            }

            // FIXME not sure if that's such a good idea ;-)
            foreach($info['pages'] as $page) {
                $info['meta'][]      = metaFiles($page);
                $info['backlinks'][] = ft_backlinks($page);
            }
        }

        return $info;
    }

    function pinfo($info) {
        // FIXME show locked pages
        $meta = array();
        print '<ul>' . DOKU_LF;
        $pnum = count($info['pages']);
        printf('<li><div class="li">' . $this->getLang('info_pages') . '</div></li>' . DOKU_LF, $pnum);

        $bnum = count($info['backlinks']);
        printf('<li><div class="li">' . $this->getLang('info_backlinks') . '</div></li>' . DOKU_LF, $bnum);

        foreach($info['meta'] as $files) {
            foreach($files as $file) {
                list($chunk, $ext) = explode('.', $file);
                if(!$meta[$ext]) $meta[$ext] = array();
                array_push($meta[$ext], $file);
            }
        }
        foreach($meta as $type => $files) {
            $num = count($files);
            printf('<li><div class="li">' . $this->getLang('info_meta') . '</div></li>' . DOKU_LF, $num, $type);
        }
        print '</ul>' . DOKU_LF;
    }

    function check_dest($argv) {
        // FIXME check mode!!!
        if(page_exists($argv['dest'])) {
            print 'True';
        } else {
            print 'False';
        }
    }

    function move_page($argv) {
        // FIXME lock page
        // FIXME check if page is locked

        if($argv['mode'] == 'page') {
            $argv['dest'] = cleanID($argv['dest']);
            $argv['dest_fn'] = wikiFN($argv['dest']);
        }

        if($argv['mode'] == 'ns') {
            $argv['dest_ns'] = cleanID($argv['dest_ns']);
        }

        // FIXME check if destination page exists and overwrite is set

        // explicit page mode for further actions and collect page info
        $argv['mode'] = 'page';
        $info = $this->collect_info($argv);

        dbg($argv);
        dbg($info);
        // FIXME do all the dirty work
        return;

        // process actions
        foreach($argv['action'] as $action => $val) {
            call_user_func_array(array($this, 'process_' . $action), array('info' => $info, 'argv' => $argv));
        }

        // FIXME reverse render works here
        $ins = $this->prepare_instructions($argv);
        $text = p_render('pagemoveng', $ins, $info);
        dbglog($text);

        // add referencing pages to queue
        if(!empty($info['backlinks'])) {
            $this->queue_add($argv, $info['backlinks']);
        }

        // FIXME revmove lock
    }

    function prepare_instructions($argv) {
        $ins = p_cached_instructions(wikiFN($argv['id']), false, $argv['id']);
        $num = count($ins);
        for($i=0; $i<$num; $i++) {
            switch($ins[$i][0]) {
                case 'internallink':
                    resolve_pageid(getNS($argv['id']), &$ins[$i][1][0], $exists);
                    // FIXME I can't remember why I put this here - but there's a reason for it
                    if(!strpos($ins[$i][1][0], ':')) {
                        $ins[$i][1][0] = ':' . $ins[$i][1][0];
                    }
                    break;
                case 'internalmedia':
                    // FIXME - check if media is moved!!!
                    break;
                case 'plugin':
                    // FIXME allow plugins to do stuff there or do we use the renderer?
                    // probably better to use the renderer
                    break;
                default:
                    break;
            }
        }

    }

    function process_history($info, $argv) {
        msg('process_history');
        if(!$argv['action']['history']) return;
        $changes_fn      = metaFN($argv['id'], '.changes');
        $changes_dest_fn = metaFN($argv['dest'], '.changes');
        $changes = io_readFile($changes_fn);
        $changes = preg_replace('/(\s+)(' . $argv['id'] . ')(\s+)/', '\1' . $argv['dest'] . '\3', $changes);

        // FIXME uncomment
        // rename($changes_fn, $changes_dest_fn);
    }

    function process_meta($info, $argv) {
        msg('process_meta');
        if(!$argv['action']['meta']) return;
        foreach($info['meta'] as $meta_fn) {
            if(!strpos($meta_fn, '.changes') && !strpos($meta_fn, '.indexed')) {
                // FIXME issue event for plugins to handle/update their meta files else move it
                list($chunk, $ext) = explode('.', $meta_fn);
                $data['meta_fn'] = $meta_fn;
                $data['dest_fn'] = metaFN($argv['dest'], '.' . $ext);

                // FIXME uncomment and debug
                //rename($data['meta_fn'], $data['dest_fn']);
            }
        }
    }

    function process_media($info, $argv) {
        msg('process_media');
        if(!$argv['action']['media']) return;
        // FIXME move media to new namespace relative to the new page if requested
    }

    function queue_read() {
        if(file_exists($this->queue_fn)) {
            return unserialize(io_readFile($this->queue_fn));
        } else {
            return array();
        }
    }

    function queue_write($queue) {
        // FIXME do we add a .pagemove file too for easier queue processing?
        // FIXME uncomment
        //io_saveFile($this->queue_fn, serialize($queue));
    }

    function queue_add($argv, array $pages) {
        $num   = count($pages);

        $queue = $this->queue_read();
        foreach($pages as $page) {
            array_push($queue, array('id' => $page, 'timestamp' => time(), 'argv' => $argv));
        }
        // FIXME removeme
        //dbg($queue);
        $this->queue_write($queue);

        msg(sprintf($this->getLang('msg_queue_add'), $num));
    }

    function queue_del($id) {
        $this->queue_read();
        // FIXME remove id from queue
        $this->queue_write();
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
