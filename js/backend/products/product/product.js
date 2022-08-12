( function($) {

    var Page = ( function($) {

        Page = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.urls = options["urls"];
            that.templates = options["templates"];

            that.sidebar = that.initSidebar();
            that.product_uri = options["product_uri"];
            that.product_id = options["product_id"];

            // DYNAMIC VARS
            console.log( that );

            // INIT
            that.init();
        };

        Page.prototype.init = function() {
            var that = this;

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.$wrapper.trigger("ready", [that]);

            that.initCallback();

            var $product_name = that.$wrapper.find(".js-product-name");
            that.$wrapper.on("change_product_name", function(event, product_name) {
                $product_name.text(product_name);
            });
        };

        Page.prototype.initCallback = function() {
            var that = this;

            that.$wrapper.on("click", ".js-show-callback-dialog", function() {
                var $button = $(this);

                $.waDialog({
                    html: that.templates["callback_dialog"],
                    onOpen: initDialog,
                    options: {
                        onSuccess: function() {
                            $button.removeClass("animation-pulse");
                        }
                    }
                });
            });

            function initDialog($dialog, dialog) {
                var is_locked = false;

                var loading = "<span class=\"icon top\" style='margin-right: .5rem;'><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var $textarea = $dialog.find("textarea:first");

                $textarea.on("focus", function() {
                    var $textarea = $(this);

                    var placeholder = $textarea.data("placeholder");
                    if (!placeholder) {
                        placeholder = $textarea.attr("placeholder");
                        $textarea.data("placeholder", placeholder);
                    }

                    $textarea.attr("placeholder", "");
                });

                $textarea.on("blur", function() {
                    var $textarea = $(this);

                    var placeholder = $textarea.data("placeholder");
                    if (placeholder) {
                        $textarea.attr("placeholder", placeholder);
                    }
                });

                $dialog.on("click", ".js-success-button", function() {
                    var $submit_button = $(this);

                    var value = $.trim($textarea.val());
                    if (!value.length) { return false; }

                    if (!is_locked) {
                        is_locked = true;
                        var $loading = $(loading).prependTo( $submit_button.attr("disabled", true) );

                        addCallback()
                            .always( function() {
                                is_locked = false;
                                $submit_button.attr("disabled", false);
                                $loading.remove();
                            })
                            .done( function() {
                                $dialog.find(".dialog-header, .dialog-footer").remove();
                                $dialog.find(".dialog-body").html( that.templates["callback_dialog_success"] );
                                setTimeout( function() {
                                    if ($.contains(document, $dialog[0])) {
                                        dialog.options.onSuccess();
                                        dialog.close();
                                    }
                                }, 3000);
                            });
                    }
                });

                function addCallback() {
                    var href = that.urls["callback_submit"],
                        data = $dialog.find(":input").serializeArray();

                    return $.post(href, data, "json");
                }
            }
        };

        Page.prototype.initSidebar = function() {
            var that = this;

            return $.wa_shop_products.init.initProductSidebar({
                $wrapper: that.$wrapper.find(".js-page-sidebar")
            });
        };

        Page.prototype.initStickyFooter = function($footer) {
            var that = this;

            var $window = $(window),
                $observer = $("<div />");

            var sticky_class = "is-sticky";

            observe();

            $window.on("scroll section_mounted", scrollWatcher);
            function scrollWatcher() {
                var is_exist = $.contains(document, $footer[0]);
                if (is_exist) {
                    observe();
                } else {
                    $window.off("scroll section_mounted", scrollWatcher);
                }
            }

            function observe() {
                $observer.insertAfter($footer);

                var observer_top = $observer.offset().top,
                    scroll_top = $window.scrollTop(),
                    window_h = $window.height();

                if (observer_top > scroll_top + window_h) {
                    $footer.addClass(sticky_class);
                } else {
                    $footer.removeClass(sticky_class);
                }

                $observer.detach();
            }
        };

        Page.prototype.initProductDelete = function($wrapper) {
            $wrapper = (typeof $wrapper === "object" ? $wrapper : that.$wrapper);

            var that = this;

            var loading = "<span class=\"icon top\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

            $wrapper.on("click", ".js-product-delete", function(event) {
                event.preventDefault();

                var $delete_button = $(this);

                if (!that.is_locked) {
                    that.is_locked = true;

                    var $icon = $delete_button.find(".icon"),
                        $loading = $(loading);

                    $delete_button.attr("disabled", true);
                    $loading.insertBefore($icon.hide());

                    showConfirm()
                        .always( function() {
                            that.is_locked = false;
                        })
                        .done( function() {
                            that.is_locked = true;

                            request(that.urls["product_delete"], { "product_id[]": that.product_id })
                                .always( function() {
                                    $loading.remove();
                                    $icon.show();
                                    $delete_button.attr("disabled", false);
                                    that.is_locked = false;
                                })
                                .done( function() {
                                    var href = $.wa_shop_products.section_url;
                                    $.wa_shop_products.router.load(href);
                                });
                        })
                        .fail( function() {
                            $loading.remove();
                            $icon.show();
                            $delete_button.attr("disabled", false);
                        });
                }
            });

            function showConfirm() {
                var deferred = $.Deferred();
                var is_success = false;

                var data = {
                    product_id: that.product_id
                };

                $.post(that.urls["product_delete_dialog"], data, "json")
                    .done( function(html) {
                        $.waDialog({
                            html: html,
                            onOpen: function($dialog, dialog) {
                                $dialog.on("click", ".js-success-action", function(event) {
                                    event.preventDefault();
                                    is_success = true;
                                    dialog.close();
                                });
                            },
                            onClose: function() {
                                if (is_success) {
                                    deferred.resolve();
                                } else {
                                    deferred.reject();
                                }
                            }
                        });
                    });

                return deferred.promise();
            }

            function request(href, data) {
                var deferred = $.Deferred();

                $.post(href, data, "json")
                    .done( function(response) {
                        if (response.status === "ok") {
                            deferred.resolve(response.data);

                        } else {
                            if (response.errors) {
                                that.renderErrors(response.errors);
                            }
                            deferred.reject("errors", (response.errors ? response.errors: null));
                        }
                    })
                    .fail( function() {
                        deferred.reject("server_error", arguments);
                    });

                return deferred.promise();
            }
        };

        return Page;

    })($);

    $.wa_shop_products.init.initProductPage = function(options) {
        var that = this;

        return new Page(options);
    };

})(jQuery);