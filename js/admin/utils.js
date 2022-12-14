(function ($) { 
    $(function() {
        $.ajaxSetup({
            cache: false
        });

        $("#transposh-reset-options").click(function() {
            if (!confirm("Are you sure you want to do this?")) return false;
            if (!confirm("Are you REALLY sure you want to do this, your configuration will be reset?")) return false;
            $.post(ajaxurl, {
                action: 'tp_reset',
                nonce: $('#transposh_nonce').val()
            });
        });

        // backup button
        backupclick = function () {
            $("#transposh-backup").unbind('click').click(function(){
                return false
            }).text("Backup In Progress");
            $.post(ajaxurl, {
                action: 'tp_backup',
                nonce: $('#transposh_nonce').val()
            },
            function(data) {
                var color = 'red';
                if (data[0] == '2') color = 'green';
                $('#backup_result').html(data).css('color',color);
                $("#transposh-backup").unbind('click').click(backupclick).text("Do Backup Now");
            });
            return false;
        };
        $("#transposh-backup").click(backupclick);

        // cleanup button
        cleanautoclick = function (days,button) {
            if (!confirm("Are you sure you want to do this?")) return false;
            if (days == 0 && !confirm("Are you REALLY sure you want to do this?")) return false;
            //var button = $(this);
            //console.log(button);
            var prevtext = button.text();
            button.unbind('click').click(function(){
                return false
            }).text("Cleanup in progress");
            $.post(ajaxurl, {
                action: 'tp_cleanup',
                days: days,
                nonce: $('#transposh_nonce').val()
            },
            function(data) {
                button.unbind('click').click(function() {
                    cleanautoclick(days,button);
                    return false;
                }).text(prevtext);
            });
            return false;
        };

        // Dedup button
        dedupautoclick = function (button) {
            if (!confirm("Are you sure you want to do this?")) return false;
            var prevtext = button.text();
            button.unbind('click').click(function(){
                return false
            }).text("Deduplication in progress");
            $.post(ajaxurl, {
                action: 'tp_dedup',
                nonce: $('#transposh_nonce').val()
            },
            function(data) {
                button.unbind('click').click(function() {
                    dedupautoclick(button);
                    return false;
                }).text(prevtext);
            });
            return false;
        };
     
        $("#transposh-reset-proxy-timers").click(function() {
            $.post(ajaxurl, {
                action: 'tp_reset_timers',
                nonce: $('#transposh_nonce').val()
            });
            return false;
        });
        
        $("#transposh-clean-auto").click(function() {
            cleanautoclick(0,$(this));
            return false;
        });

        $("#transposh-clean-auto14").click(function() {
            cleanautoclick(14,$(this));
            return false;
        });

        $("#transposh-clean-unimportant").click(function() {
            cleanautoclick(999,$(this));
            return false;
        });

        $("#transposh-dedup").click(function() {
            dedupautoclick($(this));
            return false;
        });
        maintclick = function (button) {
            if (!confirm("Are you sure you want to do this?")) return false;
            var prevtext = button.text();
            button.unbind('click').click(function(){
                return false
            }).text("Maintenance in progress");
            $.post(ajaxurl, {
                action: 'tp_maint',
                nonce: $('#transposh_nonce').val()
            },
            function(data) {
                button.unbind('click').click(function() {
                    maintclick(button);
                    return false;
                }).text(prevtext);
            });
            return false;
        }
    
        $("#transposh-maint").click(function() {
            maintclick($(this));
            return false;
        });

//  WIP      $("#transposh-fetch").click(function() {
//            //maintclick($(this));
//            $.post(ajaxurl, {
//                action: 'tp_fetch'
//            });
//            return false;
//        });

        // translate all button
        do_translate_all = function () {
            $("#progress_bar_all").progressbar({
                value:0
            });
            stop_translate_var = false;
            // while there is a next
            // get next post to translate
            //var offset = "0";
            $("#tr_loading").data("done",true);
            $.ajaxSetup({
                cache: false
            });
            $.post({
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: "tp_translate_all",
                    nonce: $('#transposh_nonce').val()
                },
                cache: false,
                success: function (data) {
                    dotimer = function(a) {
                        $("#tr_allmsg").text('');
                        clearTimeout(timer2);
                        //console.log(a);
                        //console.log($("#tr_loading").data("done"));
                        if ($("#tr_loading").data("done") || $("#tr_loading").data("attempt")>4) {
                            $("#progress_bar_all").progressbar('value' , (a+1)/data.length*100);
                            $("#tr_loading").data("attempt",0);
                            translate_post(data[a]);
                            //console.log($("#tr_loading").data("done"));
                            //console.log("done translate" + a);
                            // we call the next translation here...
                            if (typeof data[a+1] !== 'undefined' && !stop_translate_var) {
                                //console.log("trigger translation of " +a);
                                timer2 = setTimeout(function() {
                                    dotimer(a+1)
                                },2000);
                                $("#tr_allmsg").text('Waiting 2 seconds...');
                            }
                        } else {
                            //console.log("waiting for translation to finish 60 seconds");
                            $("#tr_loading").data("attempt",$("#tr_loading").data("attempt")+1);
                            timer2 = setTimeout(function() {
                                dotimer(a)
                            },15000);
                            $("#tr_allmsg").text('Translation incomplete - Waiting 15 seconds - attempt ' + $("#tr_loading").data("attempt") + '/5');
                        }
                    }
                    timer2 = setTimeout(function() {
                        dotimer(0)
                    },0);
                }
            });
            $("#transposh-translate").text("Stop translate")
            $("#transposh-translate").unbind('click').click(stop_translate);
            return false;
        }

        stop_translate = function() {
            clearTimeout(timer2);
            stop_translate_var = true;
            $("#transposh-translate").text("Translate All Now")
            $("#transposh-translate").unbind('click').click(do_translate_all);
            return false;
        }

        $("#transposh-translate").click(do_translate_all);       

    });
}(jQuery)); 