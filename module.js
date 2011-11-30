M.local_progressreview = {

    Y: '',

    progress: '',

    savebutton: '',

    autosave_failed: false,

    filterrows: {},

    init_autosave: function (Y, savestring) {
        this.Y = Y;
        this.progress = Y.one('#progressindicator');
        strautosave = M.util.get_string('autosaving', 'local_progressreview');
        this.progress.one('#autosavelabel').setContent(strautosave);
        this.progress.setStyle('display', 'none');
        this.savebutton = Y.one('#id_save');
        strsaveactive = M.util.get_string('autosaveactive', 'local_progressreview');
        this.savebutton.set('disabled', true);
        this.savebutton.set('value', strsaveactive);
        this.savestring = savestring;
    },

    autosave: function(plugin, field, value) {

        if (!this.autosave_failed) {
            Y = this.Y;
            this.progress.setStyle('display', 'block');
            var studentid = Y.one('#id_editid').get('value');
            var sessionid = Y.one('#id_sessionid').get('value');
            var courseid = Y.one('#id_courseid').get('value');
            var teacherid = Y.one('#id_teacherid').get('value');
            var reviewtype = Y.one('#id_reviewtype').get('value');

            var url = M.cfg.wwwroot+'/local/progressreview/autosave.php';
            Y.io(url, {
                data: 'studentid='+studentid+'&sessionid='+sessionid
                    +'&courseid='+courseid+'&teacherid='+teacherid
                    +'&reviewtype='+reviewtype+'&plugin='+plugin
                    +'&field='+field+'&value='+value,
                context: this,
                method: 'post',
                on: {
                    success: function(id, o) {
                        this.progress.setStyle('display', 'none');
                    },

                    failure: function(id, o) {
                        var message = o.responseText;
                        alert(M.util.get_string('autosavefailed', 'local_progressreview', message));
                        this.savebutton.set('disabled', false);
                        this.savebutton.set('value', this.savestring);
                        this.progress.setStyle('display', 'none');
                        this.autosave_failed = true;
                    }
                }
            });
        }
    },

    init_delete: function(Y, sesskey, session) {
        this.Y = Y;
        Y.one('#subjectreviews').delegate('click', this.confirm_delete, '.delete', this, sesskey, session);
        Y.one('#tutorreviews').delegate('click', this.confirm_delete, '.delete', this, sesskey, session);
    },

    confirm_delete: function(e, sesskey, session) {
        Y = this.Y;
        e.preventDefault();
        url = e.currentTarget.get('href');
        row = e.currentTarget.get('parentNode').get('parentNode');
        cells = row.get('children');
        course = cells.shift().get('textContent');
        teacher = cells.shift().get('textContent');

        strparams = {
            'teacher': '<strong>'+teacher+'</strong>',
            'course': '<strong>'+course+'</strong>',
            'session': '<strong>'+session+'</strong>'
        };
        strconfirm = M.util.get_string('confirmdelete', 'local_progressreview', strparams);

        confirmurl = url+'&confirm=1&sesskey='+sesskey;

        dialog = new YAHOO.widget.SimpleDialog("simpledialog1", {
            width: "500px",
            fixedcenter: true,
            visible: false,
            draggable: false,
            close: false,
            text: strconfirm,
            icon: YAHOO.widget.SimpleDialog.ICON_HELP,
            constraintoviewport: true,
            buttons: [
                {
                    text: M.util.get_string('continue', 'moodle'),
                    handler:function() {
                        this.hide();
                        window.location.href = confirmurl;
                    }
                },
                {
                    text: M.util.get_string('cancel', 'moodle'),
                    handler:function() {
                        this.hide();
                    },
                    isDefault: true
                }
            ]
        });
        dialog.render( document.body );
        dialog.show();

    },

    init_filters: function(Y) {
        this.filterrows = Y.all('tbody tr');
        Y.one('#filterfields').delegate('keypress', function(e) {
            id = e.currentTarget.get('id');
            filter = new RegExp(e.currentTarget.get('value'), 'i');
            if (id == 'filtercourse') {
                filtercol = 0;
            } else if (id == 'filterteacher') {
                filtercol = 1;
            }
            this.filterrows.each(function(row) {
                if (row._node.cells[filtercol].textContent.match(filter) === null) {
                    row.setStyle('display', 'none');
                } else {
                    row.setStyle('display', 'table-row');
                }
            });
        }, 'input', this);
    }
}
