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
        $progressbar: null,
        $progress_text: null,

        init() {
            const that = this;
            $.shop.trace('$.importexport.plugins.syrrss.init');
        },

        _initImageSizeSelector() {
            const $image_size_input = this.$form.find('.js-image-size-input'),
                $image_sizes_selector = $('.js-image-size-select', this.$form),
                selected_image_size = $image_size_input.val();

            $image_sizes_selector.val(selected_image_size);
            $image_sizes_selector.off('.syrrss').on('change.syrrss', evt => $image_size_input.val(evt.target.value));
        },

        _initImageQuantitySelector() {
            const $image_quantity_selector = $('.js-image-quantity-selector', this.$form),
                $max_images_input = $image_quantity_selector.first().closest('.js-field-container').find('.js-max-images-input');

            $image_quantity_selector
                .off('.syrrss')
                .on('change.syrrss', evt => $max_images_input.prop('disabled', evt.target.value !== 'max'));

            $image_quantity_selector.filter(':checked').change();
        },

        _initProgressbar() {
            this.$progressbar = $('.js-progressbar', this.$form);
            this.$progress_text = $('.js-progress-counter', this.$form);
        },

        _setProgressbar(value, max) {
            let text = '';
            if (value || value === 0 || value === '0') {
                this.$progressbar.attr('value', value);
                text = value.toString() + ' / ';
            } else {
                this.$progressbar.removeAttr('value');
                text = '? /';
            }

            if (max) {
                this.$progressbar.attr('max', max);
                text = text + max;
            } else {
                this.$progressbar.removeAttr('max');
                text = text + ' ?';
            }

            this.$progress_text.text(text);
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
            const that = this;

            this.$form = $("#s-plugin-syrrss");

            this._initImageSizeSelector();
            //
            this._initImageQuantitySelector();
            //
            this._initProgressbar();
            //
            $.importexport.products.init(this.$form);
            //
            this.$form
                .off('submit.syrrss')
                .on('submit.syrrss', function () {
                    that._progressHandler();
                    return false;
                });

            // this.$form.off('submit.syrrss').on('submit.syrrss', function (event) {
            //     $.shop.trace('submit.syrrss ' + event.namespace, event);
            //     try {
            //         const $form = $(this);
            //         $form.find(':input, :submit').attr('disabled', false);
            //
            //         $.importexport.plugins.syrrss.syrrssHandler(this);
            //     } catch (e) {
            //         $('#plugin-syrrss-transport-group').find(':input').attr('disabled', false);
            //         $.shop.error('Exception: ' + e.message, e);
            //     }
            //     return false;
            // });

        },

        _progressHandler() {
            const $progress_container = $('.js-progressbar-container', this.$form),
                $submit_button = $('.js-submit-button', this.$form),
                $error_message = $('.js-error-message', this.$form),
                $report = $('.js-report', this.$form),
                $submit_field = $('.js-submit-field', this.$form),
                url = this.$form.attr('action'),
                data = this.$form.serialize(),
                timers_pool = [],
                that = this;

            let process_id = null;

            this._setProgressbar();
            $submit_button.hide();
            $error_message.hide();
            $progress_container.show();

            $.ajax({
                url: url,
                data: data,
                dataType: 'json',
                type: 'post',
                success: response => {
                    if (response.error) {
                        $submit_button.show();
                        $error_message.text(response.error);
                        $error_message.show();
                    } else if (response.processId) {
                        process_id = response.processId;
                        //that._setProgressbar(response.processed_count ?? null, response.count ?? null);
                        step(2000);
                    } else {

                    }
                },
                error: () => {
                }
            });

            function processHandler(response) {

            }

            function clearAllTimers() {
                while (timers_pool.length > 0) {
                    const timer_id = this.timers_pool.shift();
                    if (timer_id) clearTimeout(timer_id);
                }
            }

            function cleanup() {
                $.post(
                    url,
                    {processId: process_id, cleanup: 1},
                    r => {
                        if(r.report) {
                            $report.show();
                            $submit_field.hide();
                        }
                    },
                    'json'
                ).always(() => {
                    clearAllTimers();
                    $progress_container.hide();
                    // $submit_button.show();
                });
            }

            function progress(response) {
                that._setProgressbar(response.processed_count ?? null, response.count ?? null);
            }

            function step(delay) {
                delay = delay || 2000;
                const timer = setTimeout(() => {
                    $.post(
                        url,
                        {processId: process_id},
                        r => {
                            if (!r) {
                                step(delay);
                            } else if (r.ready) {
                                cleanup(r);
                            } else if (r.error) {

                            } else if (r.progress) {
                                progress(r);
                                step(delay);
                            } else {
                                step(delay);
                            }
                        },
                        'json'
                    );
                }, delay);
            }
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
