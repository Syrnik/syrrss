// noinspection DuplicatedCode

$.extend($.importexport.plugins, {
    syrrss: {
        form: null,
        ajax_pull: {},
        progress: false,
        id: null,
        debug: {
            'memory': 0.0,
            'memory_avg': 0.0
        },
        $form: null,
        init() {
            $.shop.trace('$.importexport.plugins.syrrss.init');
            this.$form = $("#s-plugin-syrrss");
            const selected_image_size = this.$form.find('input[type=text][name=config\\[image_size\\]]').val();
            const that = this;
            const $image_select = $('#s-plugin-syrrss-image-size-select');
            $image_select.val(selected_image_size);
            $image_select.off().on('change', function () {
                const $input = that.$form.find('input[type=text][name=config\\[image_size\\]]');
                $input.val($(this).val());
            });

            // радио и инпут с кол-вом фото
            const $radio_images = $('input[name=config\\[images_count_type\\]]');
            $radio_images.off().on('change', function () {
                const is_max = (this.value === 'max');
                $('input[name=config\\[images_count_value\\]]').prop('readonly', !is_max).prop('required', is_max);
            });
            $radio_images.first().change();
        },

        hashAction(hash) {
            $.importexport.products.action(hash);
            window.location.hash = window.location.hash.replace(/\/hash\/.+$/, '/');
        },

        blur() {
        },
        action() {
        },

        // Инициализация процесса экспорта
        onInit() {
            $.importexport.products.init(this.$form);

            this.$form.off('submit.syrrss').on('submit.syrrss', function (event) {
                $.shop.trace('submit.syrrss ' + event.namespace, event);
                try {
                    const $form = $(this);
                    $form.find(':input, :submit').attr('disabled', false);

                    $.importexport.plugins.syrrss.syrrssHandler(this);
                } catch (e) {
                    $('#plugin-syrrss-transport-group').find(':input').attr('disabled', false);
                    $.shop.error('Exception: ' + e.message, e);
                }
                return false;
            });

        },

        syrrssHandler(element) {
            const self = this;
            self.progress = true;
            self.form = $(element);
            const data = self.form.serialize();
            self.form.find('.errormsg').text('');
            self.form.find(':input').attr('disabled', true);
            self.form.find(':submit').hide();
            self.form.find('.progressbar .progressbar-inner').css('width', '0%');
            self.form.find('.progressbar').show();
            const url = $(element).attr('action');
            $.ajax({
                url: url,
                data: data,
                dataType: 'json',
                type: 'post',
                success: function (response) {
                    if (response.error) {
                        self.form.find(':input').attr('disabled', false);
                        self.form.find(':submit').show();
                        self.form.find('.js-progressbar-container').hide();
                        self.form.find('.shop-ajax-status-loading').remove();
                        self.progress = false;
                        self.form.find('.errormsg').text(response.error);
                    } else {
                        self.form.find('.progressbar').attr('title', '0.00%');
                        self.form.find('.progressbar-description').text('0.00%');
                        self.form.find('.js-progressbar-container').show();

                        self.ajax_pull[response.processId] = [];
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            $.wa.errorHandler = function (xhr) {
                                // noinspection EqualityComparisonWithCoercionJS
                                return !((xhr.status >= 500) || (xhr.status == 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 100));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId, null);
                        }, 2000));
                    }
                },
                error: function () {
                    self.form.find(':input').attr('disabled', false);
                    self.form.find(':submit').show();
                    self.form.find('.js-progressbar-container').hide();
                    self.form.find('.shop-ajax-status-loading').remove();
                    self.form.find('.progressbar').hide();
                }
            });
            return false;
        },

        onDone(url, processId, response) {

        },

        progressHandler(url, processId, response) {
            // display progress
            // if not completed do next iteration
            const self = $.importexport.plugins.syrrss;
            let $bar;
            if (response && response.ready) {
                $.wa.errorHandler = null;
                let timer;
                while (timer = self.ajax_pull[processId].pop()) {
                    if (timer) clearTimeout(timer);
                }
                $bar = self.form.find('.progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                $.shop.trace('cleanup', response.processId);

                $.ajax({
                    url: url,
                    data: {
                        'processId': response.processId,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        $.shop.trace('report', response);
                        $("#plugin-syrrss-submit").hide();
                        self.form.find('.progressbar').hide();
                        const $report = $("#plugin-syrrss-report");
                        $report.show();
                        if (response.report) {
                            $report.find(".value:first").html(response.report);
                        }
                        $.storage.del('shop/hash');
                    }
                });

            } else if (response && response.error) {

                self.form.find(':input').attr('disabled', false);
                self.form.find(':submit').show();
                self.form.find('.js-progressbar-container').hide();
                self.form.find('.shop-ajax-status-loading').remove();
                self.form.find('.progressbar').hide();
                self.form.find('.errormsg').text(response.error);

            } else {
                let $description;
                if (response && (typeof (response.progress) != 'undefined')) {
                    $bar = self.form.find('.progressbar .progressbar-inner');
                    const progress = parseFloat(response.progress.replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    const title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';

                    const message = response.progress;

                    $bar.parents('.progressbar').attr('title', response.progress);
                    $description = self.form.find('.progressbar-description');
                    $description.text(message);
                    $description.attr('title', title);
                }
                if (response && (typeof (response.warning) != 'undefined')) {
                    $description = self.form.find('.progressbar-description');
                    $description.append('<i class="icon16 exclamation"></i><p>' + response.warning + '</p>');
                }

                const ajax_url = url;
                const id = processId;

                self.ajax_pull[id].push(setTimeout(function () {
                    $.ajax({
                        url: ajax_url,
                        data: {
                            'processId': id
                        },
                        dataType: 'json',
                        type: 'post',
                        success(response) {
                            self.progressHandler(url, response ? response.processId || id : id, response);
                        },
                        error() {
                            self.progressHandler(url, id, null);
                        }
                    });
                }, 500));
            }
        }
    }
});
