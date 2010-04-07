/**
 * Javascript for DokuWiki Plugin pagemoveng
 *
 * @author Michael Klier <chi@chimeric.de>
 */

var pagemoveng = {
    btn_popup: null,
    btn_cancel: null,
    btn_move: null,
    dest: null,
    mode: null,
    popup: null,
    sack: null,
    id: null,
    pages: null,
    progress: null,
    progressbar: null,
    argv: new Array(),

    init: function() {
        pagemoveng.sack = new sack(DOKU_BASE + 'lib/exe/ajax.php');
        pagemoveng.sack.AjaxFailedAlert = '';
        pagemoveng.sack.encodeURIString = false;

        pagemoveng.popup = $('plugin__pagemoveng_popup');

        pagemoveng.attach_btn_popup();
    },

    attach_btn_popup: function() {
        pagemoveng.btn_popup = $('plugin__pagemoveng_btn_popup');
        if(!pagemoveng.btn_popup) return;
        addEvent(pagemoveng.btn_popup, 'click', pagemoveng.open_popup);
    },

    attach_btn_cancel: function() {
        pagemoveng.btn_cancel = $('plugin__pagemoveng_btn_cancel');
        if(!pagemoveng.btn_cancel) return;
        addEvent(pagemoveng.btn_cancel, 'click', pagemoveng.close_popup);
    },

    attach_btn_move: function() {
        pagemoveng.btn_move = $('plugin__pagemoveng_btn_move');
        if(!pagemoveng.btn_move) return;
        addEvent(pagemoveng.btn_move, 'click', pagemoveng.validate);
    },

    attach_btn_move_page: function() {
        pagemoveng.btn_move_page = $('plugin__pagemoveng_btn_move_page');
        if(!pagemoveng.btn_move_page) return;
        addEvent(pagemoveng.btn_move_page, 'click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            pagemoveng.mode = 'page';
            pagemoveng.load_form();
            return false;
        });
    },

    attach_btn_move_ns: function() {
        pagemoveng.btn_move_ns = $('plugin__pagemoveng_btn_move_ns');
        if(!pagemoveng.btn_move_ns) return;
        addEvent(pagemoveng.btn_move_ns, 'click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            pagemoveng.mode = 'ns';
            pagemoveng.load_form();
            return false;
        });
    },

    load_form: function() {
        pagemoveng.sack.setVar('call', 'pagemoveng_load_form');
        pagemoveng.sack.setVar('pagemove[id]', pagemoveng.id);
        pagemoveng.sack.setVar('pagemove[mode]', pagemoveng.mode);

        pagemoveng.popup.innerHTML = '<img src="'+DOKU_BASE+'lib/images/loading.gif" alt="..." class="load" />';

        pagemoveng.sack.onCompletion = function(){
            var data = this.response;
            if(data === ''){ return; }
            pagemoveng.popup.innerHTML = data;
            pagemoveng.attach_btn_cancel();
            pagemoveng.attach_btn_move();
            pagemoveng.progress = $('plugin__pagemoveng_progress');
            pagemoveng.progressbar = $('plugin__pagemoveng_progressbar');
        };

        pagemoveng.sack.runAJAX();
    },

    open_popup: function(e) {
        e.stopPropagation();
        e.preventDefault();

        pagemoveng.sack.setVar('call', 'pagemoveng_popup');
        pagemoveng.prepare_ajax('plugin__pagemoveng_btn');

        pagemoveng.sack.onCompletion = function(){
            var data = this.response;
            if(data === ''){ return; }
            pagemoveng.popup.style.visibility = 'hidden';
            pagemoveng.popup.innerHTML = data;
            pagemoveng.popup.style.visibility = 'visible';
            pagemoveng.attach_btn_cancel();
            pagemoveng.attach_btn_move_page();
            pagemoveng.attach_btn_move_ns();
            // we dunno if we got straight to the move form
            pagemoveng.attach_btn_move();
        };

        pagemoveng.sack.runAJAX();
        return false;
    },

    close_popup: function(e) {
        e.stopPropagation();
        e.preventDefault();
        pagemoveng.popup.style.visibility = 'hidden';
        pagemoveng.popup.innerHTML = '';
        return false;
    },

    validate: function(e) {
        pagemoveng.dest = $('plugin__pagemoveng_dest');
        e.stopPropagation();
        e.preventDefault();
        if(pagemoveng.dest.value == '') {
            pagemoveng.dest.focus();
            alert('validateme');
            return false;
        } else {
            pagemoveng.check_dest(pagemoveng.dest.value);
            return false;
        }
    },

    check_dest: function(id) {
        pagemoveng.sack.setVar('call', 'pagemoveng_check_dest');
        pagemoveng.prepare_ajax('plugin__pagemoveng_dialog');
        pagemoveng.sack.onCompletion = function(){
            if(this.response == 'False') {
                pagemoveng.progressbar.innerHTML = '<img src="'+DOKU_BASE+'lib/images/loading.gif" alt="..." class="load" />';
                pagemoveng.move();
            } else {
                // FIXME
                alert('page exists - if youre sure check overwrite');
                pagemoveng.dest.focus();
            }
            return false;
        };
        pagemoveng.sack.runAJAX();
    },

    move: function() {
        pagemoveng.sack.setVar('call', 'pagemoveng_move_page');
        pagemoveng.page = pagemoveng.pages.shift();

        if(pagemoveng.page) {
            pagemoveng.sack.onCompletion = pagemoveng.update_progress;
            pagemoveng.sack.setVar('pagemove[page]', pagemoveng.page);
            pagemoveng.sack.setVar('pagemove[id]', pagemoveng.id);
            pagemoveng.sack.setVar('pagemove[mode]', pagemoveng.mode);
            for(var i = 0; i<pagemoveng.argv.length; i++) {
                pagemoveng.sack.setVar(pagemoveng.argv[i], 1);
            }
            pagemoveng.sack.runAJAX();
        } else {
            alert('done');
            pagemoveng.progressbar.innerHTML = '';
            pagemoveng.progressbar.style.display = 'none';
            // FIXME redirect????
        }
    },

    update_progress: function() {
        // FIXME use correct response states
        var response = this.response;
        pagemoveng.progress.innerHTML += response + '<br />';
        window.setTimeout("pagemoveng.move()", 1000);
    },

    prepare_ajax: function(div) {
        var form         = $(div);
        var inputs       = form.getElementsByTagName('input');
        pagemoveng.pages = new Array();

        for(var i=0; i<inputs.length; i++) {
            if(inputs[i].type == 'submit') continue;
            if(inputs[i].name == 'pagemove[pages][]') {
                pagemoveng.pages.unshift(inputs[i].value);
                continue;
            }
            if(inputs[i].type == 'checkbox' && inputs[i].checked) {
                pagemoveng.argv.unshift(inputs[i].name);
            }
            if(inputs[i].name == 'pagemove[mode]') pagemoveng.mode = inputs[i].value;
            if(inputs[i].name == 'pagemove[id]') pagemoveng.id = inputs[i].value;
            pagemoveng.sack.setVar(inputs[i].name, inputs[i].value);
        }
    },
};

addInitEvent(function() {
    pagemoveng.init()
});

// vim:ts=4:sw=4:et:enc=utf-8
