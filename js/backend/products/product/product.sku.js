( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            //
            that.max_file_size = options["max_file_size"];
            that.max_post_size = options["max_post_size"];

            // VUE JS MODELS
            that.stocks_array = options["stocks"];
            that.stocks = $.wa.construct(that.stocks_array, "id");
            that.product = formatProduct(options["product"]);
            that.currencies = options["currencies"];
            that.selectable_features = options["selectable_features"];
            that.states = {
                load: {
                    selectable_features: false
                }
            };
            that.errors = {};

            //
            that.new_modification = that.formatModification(options["new_modification"]);
            that.new_sku = formatSku(options["new_sku"]);

            // INIT
            that.vue_model = that.initVue();
            that.init();

            console.log( that );

            function formatProduct(product, stocks) {
                // product.normal_mode = false;
                // product.normal_mode_switch = false;

                product.badge_form = false;
                product.badge_id = (typeof product.badge_id === "string" ? product.badge_id : null);
                product.badge_prev_id = null;

                // Проставляю параметры с дефолтными значениями
                $.each(product.features, function(i, feature) {
                    if (feature.can_add_value) {
                        var white_list = ["select", "checkbox"];
                        if (white_list.indexOf(feature.render_type) >= 0) {
                            feature.show_form = false;
                            feature.form = { value: "" }

                            if (feature.type === "color") { feature.form.code = ""; }
                            if (feature.type.indexOf("dimension") >= 0) { feature.form.unit = feature.active_unit.value; }
                        }
                    }
                });

                $.each(product.skus, function(i, sku) {
                    sku.errors = {};

                    $.each(sku.modifications, function(i, sku_mod) {
                        that.formatModification(sku_mod, stocks);

                        if (!product.normal_mode) {
                            sku_mod.expanded = true;
                        }
                    });
                });

                return product;
            }

            function formatSku(sku) {
                return sku;
            }
        };

        Section.prototype.init = function() {
            var that = this;

            $.each(Object.keys(that.getUniqueIndex), function(i, key) {
                delete that.getUniqueIndex[key];
            });

            var page_promise = that.$wrapper.closest(".s-product-page").data("ready");
            page_promise.done(  function(product_page) {
                var $footer = that.$wrapper.find(".js-sticky-footer");
                product_page.initProductDelete($footer);
                product_page.initStickyFooter($footer);
            });

            that.initSave();
        };

        Section.prototype.initVue = function() {
            var that = this;

            // DOM
            var $view_section = that.$wrapper.find(".js-product-sku-section");

            // VARS
            var is_root_locked = false;

            // COMPONENTS

            Vue.component("component-advanced-sku-mode", {
                props: ["product"],
                template: that.templates["component-advanced-sku-mode"],
                delimiters: ['{ { ', ' } }'],
                methods: {
                    showMinimalModeMessage: function() {
                        $.waDialog({
                            html: that.templates["dialog_minimal_mode_message"]
                        });
                    }
                },
                mounted: function() {
                    var self = this;

                    var $switch = $(self.$el);

                    $switch.waSwitch({
                        change: function(active, wa_switch) {
                            self.$emit("change", active);
                        }
                    });

                    var switch_controller = $switch.data("switch");

                    self.switch_controller = switch_controller;

                    self.$watch("product.normal_mode", function(old_value, new_value) {
                        switch_controller.disable(!new_value);
                    });

                    self.$watch("product.skus", function(old_value, new_value) {

                    });
                }
            });

            Vue.component("component-toggle", {
                props: ["options", "active"],
                template: that.templates["component-toggle"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;
                    $(self.$el).waToggle({
                        use_animation: false,
                        change: function(event, target) {
                            self.$emit("change", $(target).data("id"));
                        }
                    });
                }
            });

            Vue.component("component-feature", {
                props: ["product", "feature", "value", "vertical", "columns"],
                template: that.templates["component-feature"],
                delimiters: ['{ { ', ' } }'],
                methods: {
                    changeFeatureValues: function(feature) {
                        var self = this;

                        that.initChangeFeatureValuesDialog(self, feature);
                    },
                    resetFeatureValues: function(feature) {
                        var self = this;

                        $.each(feature.options, function(i, option) {
                            option.active = false;
                        });

                        that.$wrapper.trigger("change");
                    },
                    getUsedFeatureValues: function(target_feature) {
                        var self = this,
                            values = [],
                            sorted_values = [];

                        if (target_feature.options && target_feature.options.length) {
                            $.each(self.product.skus, function(i, sku) {
                                $.each(sku.modifications, function(j, sku_mod) {
                                    var features_array = [].concat(sku_mod.features, sku_mod.features_selectable);
                                    $.each(features_array, function(k, feature) {
                                        if (feature.code === target_feature.code) {
                                            if (feature.active_option && feature.active_option.value) {
                                                if (values.indexOf(feature.active_option.name) < 0) {
                                                    values.push(feature.active_option.name);
                                                }
                                            } else {
                                                $.each(feature.options, function(n, option) {
                                                    if (option.active && option.value) {
                                                        if (values.indexOf(option.name) < 0) {
                                                            values.push(option.name);
                                                        }
                                                    }
                                                });
                                            }
                                            return false;
                                        }
                                    });
                                });
                            });

                            $.each(target_feature.options, function(i, option) {
                                var index = values.indexOf(option.name);
                                if (index >= 0) {
                                    sorted_values.push(values[index]);
                                }
                            });
                        }

                        return sorted_values;
                    }
                },
                mounted: function() {
                    var self = this;
                }
            });

            Vue.component("component-features", {
                props: ["product", "features", "values", "vertical", "columns"],
                template: that.templates["component-features"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;
                    initFeatureTooltips(self);
                }
            });

            Vue.component("component-dropdown-currency", {
                props: ["currency_code", "currencies", "wide"],
                template: that.templates["component-dropdown-currency"],
                data: function() {
                    return {
                        wide: (typeof wide === "boolean" ? wide : false)
                    }
                },
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    $(self.$el).waDropdown({
                        hover: false,
                        items: ".dropdown-item",
                        change: function(event, target, dropdown) {
                            self.$emit("change", $(target).data("id"));
                        }
                    });
                }
            });

            Vue.component("dropdown-units", {
                props: ["units", "default_value"],
                template: that.templates["component-dropdown-units"],
                data: function() {
                    var self = this;

                    var filter_array = self.units.filter( function(unit) {
                        return (unit.value === self.default_value);
                    });

                    var active_unit = (filter_array.length ? filter_array[0] : self.units[0]);

                    return {
                        active_unit: active_unit
                    }
                },
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    $(self.$el).waDropdown({
                        hover: false,
                        items: ".dropdown-item",
                        change: function(event, target, dropdown) {
                            var value = $(target).data("value");

                            var filter = self.units.filter(function (unit) {
                                return (unit.value === value);
                            });

                            if (filter.length) {
                                self.$emit("change_unit", filter[0]);
                            } else {
                                console.error("Unit undefined");
                            }
                        }
                    });
                }
            });

            Vue.component("dropdown-feature-options", {
                props: ["feature", "columns"],
                template: that.templates["dropdown-feature-options"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    $(self.$el).waDropdown({
                        hover: false,
                        items: ".js-set-dropdown-item",
                        change: function(event, target, dropdown) {
                            var value = $(target).data("value");
                            value = (typeof value === "undefined" ? "" : value);
                            value += "";

                            var filter = self.feature.options.filter( function(option) {
                                return (option.value === value);
                            });
                            if (filter.length) {
                                self.feature.active_option = filter[0];
                            }
                        }
                    });
                }
            });

            Vue.component("date-picker", {
                props: ["value"],
                template: that.templates["component-date-picker"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    // v-bind:value="value"
                    // v-on:input="$emit('input', $event.target.value)"

                    $(self.$el).find(".js-date-picker").each( function(i, field) {
                        var $field = $(field),
                            $alt_field = $field.parent().find("input[type='hidden']");

                        $field.datepicker({
                            altField: $alt_field,
                            altFormat: "yy-mm-dd",
                            changeMonth: true,
                            changeYear: true
                        });

                        if (self.value) {
                            var date = formatDate(self.value);
                            $field.datepicker( "setDate", date);
                        }

                        $field.on("change", function() {
                            var field_value = $field.val();
                            if (!field_value) { $alt_field.val(""); }
                            var value = $alt_field.val();
                            self.$emit("input", value);
                        });

                        function formatDate(date_string) {
                            if (typeof date_string !== "string") { return null; }

                            var date_array = date_string.split("-"),
                                year = date_array[0],
                                mount = date_array[1] - 1,
                                day = date_array[2];

                            return new Date(year, mount, day);
                        }
                    });
                }
            });

            Vue.component("color-picker", {
                props: ["value"],
                data: function() {
                    return {
                        extended: false
                    };
                },
                template: that.templates["component-color-picker"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    var $document = $(document),
                        $wrapper = $(self.$el),
                        $field = $wrapper.find(".js-color-field"),
                        $toggle = $wrapper.find(".js-color-toggle"),
                        $picker = $wrapper.find(".js-color-picker");

                    var is_ready = false;

                    var farbtastic = $.farbtastic($picker, function(color) {
                        $field.val(color);
                        updateColor();
                        if (is_ready) {
                            $field.trigger("change", [true]);
                        }
                    });

                    $toggle.on("click", function(event) {
                        event.preventDefault();
                        toggle(!self.extended);
                    });

                    $field.on("focus", function() {
                        if (!self.extended) {
                            toggle(true);
                        }
                    });

                    $field.on("input", function(event, is_my_event) {
                        if (!is_my_event) {
                            updateColor(is_my_event);
                        } else {
                            self.$emit("input", $field.val());
                        }
                    });

                    $document.on("click", clickWatcher);
                    function clickWatcher(event) {
                        var is_exist = $.contains(document, $wrapper[0]);
                        if (is_exist) {
                            if (self.extended) {
                                if (!$.contains($wrapper[0], event.target)) {
                                    toggle(false);
                                }
                            }
                        } else {
                            $document.off("click", clickWatcher);
                        }
                    }

                    if (self.value) {
                        $field.val(self.value);
                        updateColor();
                    }

                    is_ready = true;

                    function updateColor() {
                        var color = $field.val();
                        $toggle.css("background-color", color);
                        farbtastic.setColor(color);
                        self.$emit("input", color);
                    }

                    function toggle(show) {
                        self.extended = show;
                    }
                }
            });

            Vue.component("feature-color-picker", {
                props: ["data", "property"],
                data: function() {
                    return {
                        extended: false
                    };
                },
                template: that.templates["component-feature-color-picker"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    var $document = $(document),
                        $wrapper = $(self.$el),
                        $field = $wrapper.find(".js-color-field"),
                        $toggle = $wrapper.find(".js-color-toggle"),
                        $picker = $wrapper.find(".js-color-picker");

                    var farbtastic = $.farbtastic($picker, function(color) {
                        if (color !== self.data[self.property]) {
                            self.data[self.property] = color;
                            self.$emit("input", [color]);
                        }
                    });

                    $toggle.on("click", function(event) {
                        event.preventDefault();
                        toggle(!self.extended);
                    });

                    $field.on("focus", function() {
                        if (!self.extended) {
                            toggle(true);
                        }
                    });

                    $field.on("input", function(event) {
                        var color = $(this).val();
                        updateColor(color);
                        self.$emit("input", [color]);
                    });

                    $document.on("click", clickWatcher);
                    function clickWatcher(event) {
                        var is_exist = $.contains(document, $wrapper[0]);
                        if (is_exist) {
                            if (self.extended) {
                                if (!$.contains($wrapper[0], event.target)) {
                                    toggle(false);
                                }
                            }
                        } else {
                            $document.off("click", clickWatcher);
                        }
                    }

                    if (self.data[self.property]) {
                        updateColor();
                    }

                    function updateColor(color) {
                        color = (typeof color === "string" ? color : self.data[self.property]);

                        if (color !== self.data[self.property]) {
                            self.data[self.property] = color;
                        }

                        farbtastic.setColor(color);
                    }

                    function toggle(show) {
                        self.extended = show;
                    }
                }
            });

            Vue.component("component-switch", {
                props: ["value", "disabled"],
                data: function() {
                    return {
                        checked: (typeof this.value === "boolean" ? this.value : false),
                        disabled: (typeof this.disabled === "boolean" ? this.disabled : false)
                    };
                },
                template: that.templates["component-switch"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    $(self.$el).waSwitch({
                        change: function(active, wa_switch) {
                            self.$emit("change", active);
                            self.$emit("input", active);
                        }
                    });
                }
            });

            Vue.component("component-generator-feature", {
                props: ["feature"],
                template: that.templates["component-generator-feature"],
                delimiters: ['{ { ', ' } }'],
                methods: {
                    toggleHiddenContent: function() {
                    },
                    addFeatureOption: function(event) {
                        var self = this;

                        var $button = $(event.currentTarget),
                            $list = $button.closest(".s-options-list");

                        $button.hide();

                        var $form = $(that.templates["add-feature-value-form"]).insertAfter($list);

                        initForm($form)
                            .always( function() {
                                $form.remove();
                                $button.show();
                            })
                            .done( function(data) {
                                // add data to root model
                                that.addFeatureValueToModel(self.feature, data.option);

                                // add data to dialog model
                                self.feature.options.push({
                                    name: data.option.name,
                                    value: data.option.value,
                                    active: true
                                });
                            });

                        function initForm($form) {
                            var deferred = $.Deferred();

                            var $submit_button = $form.find(".js-submit-button"),
                                $field = $form.find(".js-field"),
                                $icon = $submit_button.find(".s-icon");

                            var loading = "<span class=\"icon\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                            var is_locked = false;

                            $field.val("").trigger("focus");

                            //

                            $submit_button.on("click", function(event) {
                                event.preventDefault();

                                var value = $.trim($field.val());
                                if (!value.length) { return false; }

                                if (!is_locked) {
                                    is_locked = true;

                                    var $loading = $(loading).insertAfter( $icon.hide() );
                                    $submit_button.attr("disabled", true);

                                    var data = {
                                        "feature_id": self.feature.id,
                                        "value": value
                                    };

                                    that.addFeatureValueRequest(data)
                                        .always( function() {
                                            $submit_button.attr("disabled", false);
                                            $loading.remove();
                                            $icon.show();
                                            is_locked = false;
                                        })
                                        .done( function(data) {
                                            deferred.resolve(data);
                                        });
                                }
                            });

                            $form.on("click", ".js-cancel", function(event) {
                                event.preventDefault();
                                deferred.reject();
                            });

                            //

                            return deferred.promise();
                        }

                    }
                },
                updated: function() {
                    var self = this;

                    self.$emit("feature_updated");
                },
                computed: {
                    getActiveOptionsCount: function() {
                        var self = this;

                        var active_options = self.feature.options.filter( function(option) {
                            return !!option.value.length && option.active;
                        });

                        return active_options.length;
                    }
                },
                mounted: function() {
                    var self = this;
                }
            });

            Vue.component("component-product-badge-form", {
                props: ["product"],
                data: function() {
                    var self = this;
                    return {
                        "badge": self.product.badges[self.product.badges.length - 1]
                    }
                },
                template: that.templates["component-product-badge-form"],
                methods: {
                    updateForm: function() {
                        var self = this;

                        self.badge.code = self.badge.code_model;
                        self.product.badge_form = false;

                        that.$wrapper.trigger("change");
                    },
                    revertForm: function() {
                        var self = this;

                        if (self.product.badge_prev_id) {
                            self.product.badge_id = self.product.badge_prev_id;
                            that.$wrapper.trigger("change");
                        }

                        self.product.badge_form = false;
                    }
                },
                mounted: function() {
                    var self = this;

                    var $textarea = $(self.$el).find("textarea");

                    that.toggleHeight($textarea);

                    $textarea.on("input", function() {
                        that.toggleHeight($textarea);
                    });
                }
            });

            Vue.component("component-sku-name-textarea", {
                props: ["sku"],
                template: that.templates["component-sku-name-textarea"],
                methods: {
                },
                updated: function() {
                    var self = this;
                    var $textarea = $(self.$el);
                    that.toggleHeight($textarea);
                },
                mounted: function() {
                    var self = this;
                    var $textarea = $(self.$el);
                    that.toggleHeight($textarea);
                }
            });

            Vue.component("component-feature-value-form", {
                props: ["feature"],
                data: function() {
                    return {
                        is_locked: false,
                        color_xhr: null,
                        transliterate_name: true,
                        transliterate_color: true,
                        timer: 0
                    }
                },
                template: that.templates["component-feature-value-form"],
                methods: {
                    submitForm: function() {
                        var self = this;

                        if (!self.feature.form.value.length) { return; }
                        if (self.is_locked) { return; }

                        self.is_locked = true;

                        var data = {
                            "feature_id": self.feature.id,
                            "value[value]": self.feature.form.value
                        };

                        if (typeof self.feature.form.code === "string" && self.feature.form.code.length) {
                            data["value[code]"] = self.feature.form.code;
                        }

                        if (typeof self.feature.form.unit === "string" && self.feature.form.unit.length) {
                            data["value[unit]"] = self.feature.form.unit;
                        }

                        request(data)
                            .always( function() {
                                self.is_locked = false;
                            })
                            .done( function(data) {
                                self.feature.show_form = false;
                                self.feature.form.value = "";
                                if (self.feature.form.code) {
                                    self.feature.form.code = "";
                                }
                                if (self.feature.default_unit) {
                                    self.feature.form.unit = self.feature.default_unit;
                                }
                                // update all features model values
                                that.addFeatureValueToModel(self.feature, data.option);
                                self.$emit("feature_value_added", data.option);
                            });

                        function request(request_data) {
                            var deferred = $.Deferred();

                            $.post(that.urls["add_feature_value"], request_data, "json")
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        deferred.resolve(response.data);
                                    } else {
                                        deferred.reject(response.errors);
                                    }
                                })
                                .fail( function() {
                                    deferred.reject([]);
                                });

                            return deferred.promise();
                        }
                    },
                    closeForm: function() {
                        var self = this;
                        self.feature.show_form = false;
                    },
                    changeFormUnit: function(unit) {
                        var self = this;
                        self.feature.form.unit = unit.value;
                    },
                    colorChange: function() {
                        var self = this;

                        self.transliterate_color = !self.feature.form.code.length;

                        clearTimeout(self.timer);
                        self.timer = setTimeout(sendRequest, 500);

                        function sendRequest() {
                            var color = self.feature.form.code;
                            color = (typeof color === "string" ? color : "");

                            if (self.feature.form.code.length && self.transliterate_name) {
                                self.getColorInfo(null, color)
                                    .done( function(data) {
                                        if (self.transliterate_name) {
                                            self.feature.form.value = data.name;
                                        }
                                    });
                            }
                        }
                    },
                    colorNameChange: function() {
                        var self = this;

                        self.transliterate_name = !self.feature.form.value.length;

                        clearTimeout(self.timer);
                        self.timer = setTimeout(sendRequest, 500);

                        function sendRequest() {
                            var name = self.feature.form.value;
                            name = (typeof name === "string" ? name : "");

                            if (name.length && self.transliterate_color) {
                                self.getColorInfo(name, null)
                                    .done( function(data) {
                                        if (self.transliterate_color) {
                                            self.feature.form.code = data.color;
                                            self.$set(self.feature.form, "code", data.color);
                                        }
                                    });
                            }
                        }
                    },
                    getColorInfo: function(name, color) {
                        var self = this;

                        var href = that.urls["color_transliterate"],
                            data = {};

                        if (color) { data.code = color2magic(color); }
                        if (name) { data.name = name; }

                        var deferred = $.Deferred();

                        if (self.color_xhr) { self.color_xhr.abort(); }

                        self.color_xhr = $.get(href, data, "json")
                            .always( function() {
                                self.color_xhr = null;
                            })
                            .done( function(response) {
                                if (response.status === "ok") {
                                    deferred.resolve({
                                        name: response.data.name,
                                        color: magic2color(response.data.code)
                                    });
                                } else {
                                    deferred.reject();
                                }
                            })
                            .fail( function() {
                                deferred.reject();
                            });

                        return deferred.promise();

                        function color2magic(color) {
                            return 0xFFFFFF & parseInt(('' + color + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16);
                        }

                        function magic2color(magic) {
                            return (0xF000000 | magic).toString(16).toLowerCase().replace(/^f/, "#");
                        }
                    }
                },
                mounted: function() {
                    var self = this;
                }
            });

            Vue.component("component-feature-color", {
                props: ["feature", "vertical"],
                data: function() {
                    return {
                        color_xhr: null,
                        transliterate_name: true,
                        transliterate_color: true,
                        timer: 0
                    }
                },
                template: that.templates["component-feature-color"],
                methods: {
                    colorChange: function() {
                        var self = this;

                        var option = self.feature.options[0];

                        self.transliterate_color = !option.code.length;

                        clearTimeout(self.timer);
                        self.timer = setTimeout(sendRequest, 500);

                        function sendRequest() {
                            var color = option.code;
                            color = (typeof color === "string" ? color : "");

                            if (option.code.length && self.transliterate_name) {
                                self.getColorInfo(null, color)
                                    .done( function(data) {
                                        if (self.transliterate_name) {
                                            option.value = data.name;
                                        }
                                    });
                            }
                        }
                    },
                    colorNameChange: function() {
                        var self = this;

                        var option = self.feature.options[0];

                        self.transliterate_name = !option.value.length;

                        clearTimeout(self.timer);
                        self.timer = setTimeout(sendRequest, 500);

                        function sendRequest() {
                            var name = option.value;
                            name = (typeof name === "string" ? name : "");

                            if (name.length && self.transliterate_color) {
                                self.getColorInfo(name, null)
                                    .done( function(data) {
                                        if (self.transliterate_color) {
                                            option.code = data.color;
                                            self.$set(option, "code", data.color);
                                        }
                                    });
                            }
                        }
                    },
                    getColorInfo: function(name, color) {
                        var self = this;

                        var href = that.urls["color_transliterate"],
                            data = {};

                        if (color) { data.code = color2magic(color); }
                        if (name) { data.name = name; }

                        var deferred = $.Deferred();

                        if (self.color_xhr) { self.color_xhr.abort(); }

                        self.color_xhr = $.get(href, data, "json")
                            .always( function() {
                                self.color_xhr = null;
                            })
                            .done( function(response) {
                                if (response.status === "ok") {
                                    deferred.resolve({
                                        name: response.data.name,
                                        color: magic2color(response.data.code)
                                    });
                                } else {
                                    deferred.reject();
                                }
                            })
                            .fail( function() {
                                deferred.reject();
                            });

                        return deferred.promise();

                        function color2magic(color) {
                            return 0xFFFFFF & parseInt(('' + color + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16);
                        }

                        function magic2color(magic) {
                            return (0xF000000 | magic).toString(16).toLowerCase().replace(/^f/, "#");
                        }
                    }
                },
                mounted: function() {
                    var self = this;
                }
            });

            // ROOT VUE

            var vue_model = new Vue({
                el: $view_section[0],
                data: {
                    errors: that.errors,
                    states: that.states,
                    stocks: that.stocks,
                    stocks_array: that.stocks_array,
                    product: that.product,
                    currencies: that.currencies,
                    selectable_features: that.selectable_features
                },
                computed: {
                    getSkuModAvailableState: function() {
                        var self = this;

                        return function(sku, target_state) {
                            var sku_mods_count = sku.modifications.length,
                                active_sku_mods_count = sku.modifications.filter( function(sku_mod) {
                                    return sku_mod.available;
                                }).length;

                            var state = "";

                            if (sku_mods_count === active_sku_mods_count) {
                                state = "all";
                            } else if (active_sku_mods_count === 0) {
                                state = "none";
                            } else {
                                state = "part";
                            }

                            if (typeof target_state === "string") {
                                return (target_state === state);
                            } else {
                                return state;
                            }
                        }
                    },
                    getSkuModAvailableTitle: function() {
                        var self = this;

                        return function(sku, locales) {
                            var state = self.getSkuModAvailableState(sku),
                                result = "";

                            if (state === "all") {
                                result = locales[2];
                            } else if (state === "part") {
                                result = locales[1];
                            } else {
                                result = locales[0];
                            }

                            return result;
                        }
                    }
                },
                methods: {
                    removeError: function(error_id) {
                        console.log(error_id);
                        that.vue_model.$delete(that.errors, error_id);
                    },

                    // Misc
                    changeMinimalModeSwitch: function(active) {
                        var self = this;

                        that.product.normal_mode_switch = active;

                        self.resetSkusType();

                        $.each(that.product.skus, function(i, sku) {
                            if (!sku.sku) { sku.sku = getSkuSku(); }

                            $.each(sku.modifications, function(i, sku_mod) {
                                sku_mod.sku = sku.sku;
                                sku_mod.expanded = !active;
                            });
                        });

                        that.validate();
                    },
                    resetSkusType: function() {
                        $.each(that.selectable_features, function(i, feature) {
                            feature.active = false;
                        });
                        that.updateModificationSelectableFeatures(that.selectable_features);
                    },
                    changeSelectableFeatures: function() {
                        that.states.load.selectable_features = true;
                        var clone_selectable_features = JSON.parse(JSON.stringify(that.selectable_features));

                        $.waDialog({
                            html: that.templates["select_selectable_features_dialog"],
                            options: {
                                selectable_features: clone_selectable_features,
                                onSuccess: function(selectable_features) {
                                    var root_selectable_features = $.wa.construct(that.selectable_features, "id");
                                    $.each(selectable_features, function(i, feature) {
                                        root_selectable_features[feature.id].active = feature.active;
                                    });
                                    that.updateModificationSelectableFeatures(selectable_features);
                                }
                            },
                            onOpen: initDialog,
                            onClose: function() {
                                that.states.load.selectable_features = false;
                            }
                        });

                        function initDialog($dialog, dialog) {
                            var $section = $dialog.find("#vue-features-wrapper");

                            new Vue({
                                el: $section[0],
                                data: {
                                    selectable_features: dialog.options.selectable_features
                                },
                                delimiters: ['{ { ', ' } }'],
                                created: function () {
                                    $section.css("visibility", "");
                                },
                                mounted: function () {
                                    dialog.resize();
                                }
                            });

                            $dialog.on("click", ".js-submit-button", function (event) {
                                event.preventDefault();
                                dialog.options.onSuccess(dialog.options.selectable_features);
                                dialog.close();
                            });


                            initSearch();

                            function initSearch() {
                                var timer = 0;

                                var $list = $dialog.find(".s-features-list");
                                $dialog.on("keyup change", ".js-filter-field", function(event) {
                                    var $field = $(this);

                                    clearTimeout(timer);
                                    timer = setTimeout( function() {
                                        update( $field.val().toLowerCase() );
                                    }, 100);
                                });

                                function update(value) {
                                    $list.find(".s-feature-wrapper .js-name").each( function() {
                                        var $text = $(this),
                                            $item = $text.closest(".s-feature-wrapper"),
                                            is_good = ($text.text().toLowerCase().indexOf(value) >= 0);

                                        if (value.length) {
                                            if (is_good) {
                                                $item.show();
                                            } else {
                                                $item.hide();
                                            }
                                        } else {
                                            $item.show();
                                        }
                                    });

                                    dialog.resize();
                                }
                            }
                        }
                    },
                    changeViewType: function(type_id) {
                        that.product.view_type_id = type_id;
                        var active_type = that.product.view_types[that.product.view_type_id];
                        that.product.skus.forEach( function(sku) {
                            sku.expanded = active_type.expanded_sku;
                            sku.render_skus = active_type.render_skus;
                        });
                    },
                    addProductPhoto: function(event, sku_mod) {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_photo_manager"],
                            options: {
                                onPhotoAdd: function(photo) {
                                    that.product.photos.push(photo);
                                },
                                onSuccess: function(image_id) {
                                    var photo_array = that.product.photos.filter( function(photo) { return (photo.id === image_id); });
                                    if (photo_array.length) {
                                        self.setProductPhoto(photo_array[0], sku_mod);
                                    }
                                }
                            },
                            onOpen: function($dialog, dialog) {
                                that.initPhotoManagerDialog($dialog, dialog, sku_mod);
                            }
                        });
                    },
                    setProductPhoto: function(photo, sku_mod) {
                        var self = this;

                        self.$set(sku_mod, "photo", photo);
                        self.$set(sku_mod, "image_id", photo.id);
                        that.$wrapper.trigger("change");
                    },
                    removeProductPhoto: function(sku_mod) {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_sku_delete_photo"],
                            options: {
                                onUnpin: function() {
                                    self.$set(sku_mod, "photo", null);
                                    self.$set(sku_mod, "image_id", null);
                                    that.$wrapper.trigger("change");
                                },
                                onDelete: function() {
                                    var photo = sku_mod.photo;

                                    // удаляем фотку на сервере
                                    $.get(that.urls["delete_image"], { id: photo.id }, "json");

                                    // удаляем фотку из модели модификаций
                                    $.each(that.product.skus, function(i, _sku) {
                                        $.each(_sku.modifications, function(i, _sku_mod) {
                                            if (_sku_mod.photo && _sku_mod.photo.id === photo.id) {
                                                self.$set(_sku_mod, "photo", null);
                                                self.$set(_sku_mod, "image_id", null);
                                            }
                                        });
                                    });

                                    // удаляем фотку из модели продукта
                                    var index = null;
                                    $.each(that.product.photos, function(i, _photo) {
                                        if (_photo.id === photo.id) { index = i; return false; }
                                    });
                                    if (typeof index === "number") {
                                        that.product.photos.splice(index, 1);
                                    }

                                    that.$wrapper.trigger("change");
                                }
                            },
                            onOpen: initDialog
                        });

                        function initDialog($dialog, dialog) {
                            $dialog.on("click", ".js-unpin-button", function(event) {
                                event.preventDefault();
                                dialog.options.onUnpin();
                                dialog.close();
                            });

                            $dialog.on("click", ".js-delete-button", function(event) {
                                event.preventDefault();
                                dialog.options.onDelete();
                                dialog.close();
                            });
                        }
                    },
                    massSkuGeneration: function() {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_mass_sku_generation"],
                            options: {
                                vue_model: self,
                                onSuccess: function(dialog_features) {
                                    var active_features = dialog_features.filter( function(feature) { return feature.active; });
                                    var active_features_object = $.wa.construct(active_features, "id");

                                    // 1. update selectable features
                                    that.selectable_features.forEach( function(feature) {
                                        if (active_features_object[feature.id]) {
                                            feature.active = true;
                                        }
                                    });

                                    // 2. sort skus/mods
                                    var sku_features = [];
                                    var mod_features = [];

                                    active_features.forEach( function(feature) {
                                        if (feature.use_mod) {
                                            mod_features.push(feature);
                                        } else {
                                            sku_features.push(feature);
                                        }
                                    });

                                    // 3. generate skus matrix
                                    var skus_values_array = [];
                                    sku_features.forEach( function(feature) {
                                        var options_array = [];

                                        feature.options.forEach( function(option) {
                                            if (option.active && option.value.length) {
                                                option.feature_code = feature.code;
                                                options_array.push(option);
                                            }
                                        });

                                        skus_values_array.push(options_array);
                                    });

                                    var skus_values = (skus_values_array.length ? doTheJob(skus_values_array) : []);

                                    var new_skus = [];
                                    if (skus_values.length) {
                                        skus_values.forEach( function() {
                                            var new_sku = getNewSku();
                                            new_skus.push(new_sku);
                                        });
                                    } else {
                                        new_skus.push(getNewSku());
                                    }

                                    if (!new_skus.length) {
                                        var new_sku = getNewSku();
                                        new_skus.push(new_sku);
                                    }

                                    // 4. generate mods matrix
                                    if (new_skus.length) {
                                        var ready_skus = [];

                                        $.each(new_skus, function(i, new_sku) {
                                            var values_array = [],
                                                sku_values = (skus_values.length ? skus_values[i] : []);

                                            if (mod_features.length) {
                                                mod_features.forEach( function(feature) {
                                                    var options_array = [];

                                                    feature.options.forEach( function(option) {
                                                        if (option.active && option.value.length) {
                                                            option.feature_code = feature.code;
                                                            options_array.push(option);
                                                        }
                                                    });

                                                    values_array.push(options_array);
                                                });
                                            }

                                            if (sku_values.length) {
                                                sku_values.forEach( function(sku_value) {
                                                    values_array.unshift([sku_value]);
                                                });
                                            }

                                            var mods_values = doTheJob(values_array);

                                            mods_values.forEach( function(mod_values) {
                                                var new_sku_mod = getNewSkuMod(new_sku);
                                                var mod_features_object = $.wa.construct(new_sku_mod.features, "code");

                                                mod_values.forEach( function(option_value) {
                                                    var feature = mod_features_object[option_value.feature_code];
                                                    if (feature) {
                                                        var options_object = $.wa.construct(feature.options, "value");
                                                        var option = options_object[option_value.value];
                                                        if (option) {
                                                            option.active = option_value.active;
                                                            feature.active_option = option;
                                                        }
                                                    }
                                                });

                                                new_sku.modifications.push(new_sku_mod);
                                            });

                                            ready_skus.push(new_sku);
                                        });

                                        ready_skus.reverse().forEach( function(sku) {
                                            that.product.skus.unshift(sku);
                                        });
                                    }

                                    that.updateModificationSelectableFeatures(that.selectable_features);

                                    that.product.normal_mode = true;
                                }
                            },
                            onOpen: function($dialog, dialog) {
                                that.initMassSkuGenerationDialog($dialog, dialog);
                            }
                        });

                        function doTheJob(feature_values, combinations_done) {
                            feature_values = [].concat(feature_values);

                            var current_feature_values = feature_values.pop(),
                                new_combinations_done;

                            // Первый уровень рекурсии
                            if (combinations_done === undefined) {
                                new_combinations_done = current_feature_values.map(function(new_value) {
                                    return [new_value];
                                });

                            //
                            } else {
                                new_combinations_done = [];
                                current_feature_values.forEach(function(new_value) {
                                    combinations_done.forEach(function(old_values) {
                                        var new_values = old_values.slice();
                                        new_values.push(new_value);
                                        new_combinations_done.push(new_values);
                                    });
                                });
                            }

                            // Конец рекурсии, характеристик не осталось
                            if (feature_values.length <= 0) {
                                return new_combinations_done.map(function(a) {
                                    return a.reverse();
                                });

                            } else {
                                return doTheJob(feature_values, new_combinations_done);
                            }
                        }
                    },

                    // Badges
                    changeProductBadge: function(badge) {
                        var self = this;

                        self.product.badge_prev_id = self.product.badge_id;

                        if (badge) {
                            self.product.badge_form = (badge.id === "");
                            self.product.badge_id = badge.id;
                        } else {
                            self.product.badge_id = null;
                            self.product.badge_form = false;
                        }

                        that.$wrapper.trigger("change");
                    },

                    // SKU
                    addSKU: function() {
                        var new_sku = getNewSku(),
                            new_sku_mod = getNewSkuMod(new_sku);

                        new_sku.modifications.push(new_sku_mod);
                        that.product.skus.unshift(new_sku);

                        that.updateModificationSelectableFeatures(that.selectable_features, new_sku_mod);

                        that.product.normal_mode = true;

                        that.highlight("sku", { sku: new_sku });

                        that.$wrapper.trigger("change");
                    },
                    moveSKU: function(move2top, sku, sku_index) {
                        var new_index = sku_index + (move2top ? -1 : 1);
                        if (new_index < 0 || new_index >= that.product.skus.length) {
                            // any error message ?
                        } else {
                            // remove
                            that.product.skus.splice(sku_index, 1);
                            // add
                            that.product.skus.splice(new_index, 0, sku);
                        }

                        that.$wrapper.trigger("change");
                    },
                    copySKU: function(sku, sku_index) {
                        var clone_sku = clone(sku);

                        $.each(clone_sku.modifications, function(i, sku_mod) {
                            sku_mod.id = that.getUniqueIndex("sku.id", -1);
                        });

                        clone_sku.sku_id = null;

                        that.product.skus.splice(sku_index + 1, 0, clone_sku);

                        that.product.normal_mode = true;

                        that.validate();

                        that.highlight("sku", { sku: clone_sku, focus: true });

                        that.$wrapper.trigger("change");
                    },
                    removeSKU: function(event, sku, sku_index) {
                        if (!is_root_locked) {
                            var loading = "<span class=\"icon color-gray\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                            var $icon = $(event.currentTarget).find(".s-icon"),
                                $loading = $(loading).insertAfter( $icon.hide() );

                            is_root_locked = true;
                            that.showDeleteConfirm(sku)
                                .always( function() {
                                    is_root_locked = false;
                                    $loading.remove();
                                    $icon.show();
                                })
                                .done( function () {
                                    that.product.skus.splice(sku_index, 1);

                                    if (that.product.skus.length === 1 && that.product.skus[0].modifications.length === 1) {
                                        that.product.normal_mode = false;
                                    }

                                    that.validate();
                                });

                            that.$wrapper.trigger("change");
                        }
                    },
                    changeSkuSku: function(sku, sku_index) {
                        var self = this;

                        that.validate();

                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.sku = sku.sku;
                        });
                    },
                    changeSkuName: function(sku, sku_index) {
                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.name = sku.name;
                        });
                    },
                    toggleSkuModifications: function(event, sku) {
                        var self = this;

                        var $button = $(event.currentTarget);

                        var scroll_top = $(window).scrollTop(),
                            button_top = $button.offset().top,
                            visible_space = button_top - scroll_top;

                        sku.expanded = !sku.expanded;

                        self.$nextTick( function() {
                            button_top = $button.offset().top;
                            $(window).scrollTop(button_top - visible_space);
                        });
                    },

                    // MODIFICATIONS
                    skuMainToggle: function(sku, sku_index) {
                        if (sku.sku_id !== that.product.sku_id) {
                            $.each(that.product.skus, function(i, _sku) {
                                if (_sku !== sku) {
                                    _sku.sku_id = null;
                                }
                            });

                            var sku_mod = sku.modifications[0];

                            that.updateProductMainPhoto( sku_mod.image_id ? sku_mod.image_id : null );
                            that.product.sku_id = sku.sku_id = sku_mod.id;

                            that.$wrapper.trigger("change");
                        }
                    },
                    skuStatusToggle: function(sku, sku_index) {
                        var is_active = !!(sku.modifications.filter( function(sku_mod) { return (sku_mod.status === 'enabled'); }).length);

                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.status = (is_active ? "disabled" : "enabled");
                        });

                        that.$wrapper.trigger("change");
                    },
                    skuAvailableToggle: function(sku, sku_index) {
                        var is_active = !!(sku.modifications.filter( function(sku_mod) { return sku_mod.available; }).length);

                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.available = !is_active;
                        });

                        that.$wrapper.trigger("change");
                    },
                    addModification: function(sku, sku_index) {
                        var new_sku_mod = getNewSkuMod(sku);

                        sku.modifications.push(new_sku_mod);

                        that.updateModificationSelectableFeatures(that.selectable_features, new_sku_mod);

                        that.product.normal_mode = true;

                        that.highlight("sku_mod", { sku_mod: new_sku_mod });

                        that.$wrapper.trigger("change");
                    },
                    copyModification: function(sku_mod, sku_mod_index, sku, sku_index) {
                        var clone_sku_mod = clone(sku_mod);
                        clone_sku_mod.id = that.getUniqueIndex("sku.id", -1);
                        that.product.skus[sku_index].modifications.splice(sku_mod_index + 1, 0, clone_sku_mod);

                        that.product.normal_mode = true;

                        that.highlight("sku_mod", { sku_mod: clone_sku_mod });

                        that.$wrapper.trigger("change");
                    },
                    removeModification: function(event, sku_mod, sku_mod_index, sku, sku_index) {
                        if (sku.modifications.length > 1) {
                            if (!is_root_locked) {
                                var loading = "<span class=\"icon color-gray\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                                var $icon = $(event.currentTarget).find(".s-icon"),
                                    $loading = $(loading).insertAfter( $icon.hide() );

                                is_root_locked = true;
                                that.showDeleteConfirm(sku, sku_mod)
                                    .always( function() {
                                        is_root_locked = false;
                                        $loading.remove();
                                        $icon.show();
                                    })
                                    .done( function () {
                                        that.product.skus[sku_index].modifications.splice(sku_mod_index, 1);

                                        if (that.product.skus.length === 1 && that.product.skus[0].modifications.length === 1) {
                                            that.product.normal_mode = false;
                                        }
                                    });

                                that.$wrapper.trigger("change");
                            }
                        }
                    },
                    changeModificationExpand: function(sku_mod, sku_mod_index, sku, sku_index) {
                        $.each(that.product.skus, function(i, _sku) {
                            $.each(_sku.modifications, function(j, _sku_mod) {
                                var expanded = false;
                                if (sku_mod === _sku_mod) {
                                    sku_mod.expanded = !sku_mod.expanded;
                                } else {
                                    _sku_mod.expanded = false;
                                }
                            });
                        });
                    },
                    changeModificationCurrency: function(currency_code) {
                        that.product.currency = currency_code;
                    },
                    changeSkuModStocks: function(sku_mod) {
                        var stocks_count = 0,
                            is_set = false;

                        $.each(sku_mod.stock, function(stock_id, stock_value) {
                            var value = parseFloat(stock_value);
                            if (!isNaN(value)) {
                                is_set = true;
                                stocks_count += value;
                            } else {
                                value = "";
                            }
                            sku_mod.stock[stock_id] = value;
                        });

                        sku_mod.stocks_mode = is_set;
                        sku_mod.count = (is_set ? stocks_count : "");

                        that.updateModificationStocks(sku_mod);
                    },
                    focusSkuModStocks: function(sku_mod) {
                        if (Object.keys(that.stocks).length) {
                            this.modificationStocksToggle(sku_mod, true);
                        }
                    },
                    modificationMainToggle: function(sku_mod, sku_mod_index, sku, sku_index) {
                        if (sku.sku_id !== sku_mod.id) {
                            $.each(that.product.skus, function(i, _sku) {
                                if (_sku !== sku) {
                                    _sku.sku_id = null;
                                }
                            });

                            that.updateProductMainPhoto( sku_mod.image_id ? sku_mod.image_id : null );
                            that.product.sku_id = sku.sku_id = sku_mod.id;

                            that.$wrapper.trigger("change");
                        }
                    },
                    modificationStatusToggle: function(sku_mod, sku_mod_index, sku, sku_index) {
                        sku_mod.status = (sku_mod.status === "enabled" ? "disabled" : "enabled");
                        that.$wrapper.trigger("change");
                    },
                    modificationAvailableToggle: function(sku_mod, sku_mod_index, sku, sku_index) {
                        sku_mod.available = !sku_mod.available;
                        that.$wrapper.trigger("change");
                    },
                    modificationStocksToggle: function(sku_mod, expanded) {
                        sku_mod.stocks_expanded = (typeof expanded === "boolean" ? expanded : !sku_mod.stocks_expanded);
                    },

                    // OTHER
                    validate: function(event, type, data, key) {
                        var $field = $(event.target),
                            target_value = $field.val(),
                            value = (typeof target_value === "string" ? target_value : "" + target_value);

                        if (type === "number") {
                            var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", ".", ","],
                                letters_array = [],
                                divider_exist = false;

                            $.each(value.split(""), function(i, letter) {
                                if (letter === "." || letter === ",") {
                                    if (!divider_exist) {
                                        divider_exist = true;
                                        letters_array.push(letter);
                                    }
                                } else {
                                    if (white_list.indexOf(letter) >= 0) {
                                        letters_array.push(letter);
                                    }
                                }
                            });

                            value = letters_array.join("");

                            // set
                            set(value);

                        } else if (type === "integer") {
                            var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"],
                                letters_array = [];

                            $.each(value.split(""), function(i, letter) {
                                if (white_list.indexOf(letter) >= 0) {
                                    letters_array.push(letter);
                                }
                            });

                            value = letters_array.join("");

                            // set
                            set(value);
                        }

                        function set(value) {
                            Vue.set(data, key, value);
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;
                    that.initDragAndDrop(this);
                    that.initTouchAndDrop(this);
                    that.validate();
                }
            });

            return vue_model;

            // FUNCTIONS

            function getSkuSku() {
                var sku_sku = getSkuSku();

                while (check(sku_sku)) {
                    sku_sku = getSkuSku();
                }

                return sku_sku;

                function getSkuSku() {
                    var index = that.getUniqueIndex("sku.sku", 1);
                    index = (index > 9 ? index : "0" + index);
                    return that.new_sku.sku + index;
                }

                function check(sku_sku) {
                    var result = false;

                    $.each(that.product.skus, function(i, sku) {
                        if (sku.sku === sku_sku) {
                            result = true;
                            return false;
                        }
                    });

                    return result;
                }
            }

            function getNewSku() {
                var new_sku = clone(that.new_sku),
                    active_type = that.product.view_types[that.product.view_type_id];

                new_sku.id = null;
                new_sku.expanded = active_type.expanded_sku;
                new_sku.render_skus = active_type.render_skus;
                new_sku.name = "";
                new_sku.sku = getSkuSku();
                new_sku.errors = {};

                return new_sku;
            }

            function getNewSkuMod(sku) {
                var new_sku_mod = clone(that.new_modification);
                new_sku_mod.id = that.getUniqueIndex("sku.id", -1);
                new_sku_mod = that.formatModification(new_sku_mod);

                if (sku) {
                    new_sku_mod.sku = sku.sku;
                    new_sku_mod.name = sku.name;
                }

                return new_sku_mod;
            }

            function initFeatureTooltips(vue_model) {
                var $wrapper = $(vue_model.$el);

                $wrapper.find(".wa-tooltip.js-show-tooltip-on-hover").each( function() {
                    var $tooltip = $(this),
                        tooltip = $tooltip.data("tooltip");

                    if (!tooltip) {
                        $tooltip.waTooltip({ hover: true, hover_delay: 200 });
                    }
                });
            }
        };

        // Функции для работы с моделью данных

        Section.prototype.getUniqueIndex = function(name, iterator) {
            var that = this;

            name = (typeof name === "string" ? name : "") + "_index";
            iterator = (typeof iterator === "number" ? iterator : 1);

            if (typeof that.getUniqueIndex[name] !== "number") { that.getUniqueIndex[name] = 0; }

            that.getUniqueIndex[name] += iterator;

            return that.getUniqueIndex[name];
        };

        Section.prototype.formatModification = function(sku_mod) {
            var that = this;

            var stocks = that.stocks;

            sku_mod.expanded = false;
            sku_mod.stocks_expanded = false;
            sku_mod.stocks_mode = true;
            sku_mod.stocks_indicator = 1;

            if (typeof sku_mod.stock !== "object" || Array.isArray(sku_mod.stock)) {
                sku_mod.stocks_mode = false;
                sku_mod.stock = {};
            }

            $.each(stocks, function(stock_id, stock_count) {
                if (typeof sku_mod.stock[stock_id] === "undefined") {
                    sku_mod.stock[stock_id] = "";
                }
            });

            that.updateModificationStocks(sku_mod);

            return sku_mod;
        };

        Section.prototype.updateModificationSelectableFeatures = function(updated_selectable_features, sku_mod) {
            updated_selectable_features = ( typeof updated_selectable_features === "object" && updated_selectable_features.length ? updated_selectable_features : []);

            var that = this;

            updated_selectable_features = updated_selectable_features.filter( function(feature) {
                return !!feature.active;
            });

            var updated_selectable_features_object = $.wa.construct(updated_selectable_features, "id")

            if (sku_mod) {
                update(sku_mod);
            } else {
                $.each(that.product.skus, function(i, sku) {
                    $.each(sku.modifications, function(i, sku_mod) {
                        update(sku_mod);
                    });
                });
            }

            that.$wrapper.trigger("change");

            function update(modification) {
                var features_object = $.wa.construct(modification.features, "id"),
                    features_selectable_object = $.wa.construct(modification.features_selectable, "id");

                $.each(features_object, function(i, feature) {
                    if (updated_selectable_features_object[feature.id]) {
                        selectFeature(feature);
                    }
                });

                $.each(features_selectable_object, function(i, feature) {
                    if (!updated_selectable_features_object[feature.id]) {
                        unselectFeature(feature);
                    }
                });

                function selectFeature(feature) {
                    modification.features.splice(modification.features.indexOf(feature), 1);
                    modification.features_selectable.unshift(feature);
                }

                function unselectFeature(feature) {
                    modification.features_selectable.splice(modification.features_selectable.indexOf(feature), 1);
                    modification.features.unshift(feature);
                }
            }
        };

        Section.prototype.updateModificationStocks = function(sku_mod) {
            var that = this;

            if (sku_mod) {
                update(sku_mod);
            } else {
                $.each(that.product.skus, function(i, sku) {
                    $.each(sku.modifications, function(i, sku_mod) {
                        update(sku_mod);
                    });
                });
            }

            function update(sku_mod) {
                var is_good = false;
                var is_warn = false;
                var is_critical = false;

                var stocks_count = 0,
                    is_set = false;

                $.each(sku_mod.stock, function(stock_id, stock_value) {
                    var value = parseFloat(stock_value);
                    if (!isNaN(value)) {
                        is_set = true;
                        stocks_count += value;

                        var stock = that.stocks[stock_id];
                        if (value > stock.critical_count) {
                            if (value > stock.low_count) {
                                is_good = true;
                            } else {
                                is_warn = true;
                            }
                        } else {
                            is_critical = true;
                        }
                    }
                });

                if (is_critical) {
                    sku_mod.stocks_indicator = -1;
                } else if (is_warn) {
                    sku_mod.stocks_indicator = 0;
                } else {
                    sku_mod.stocks_indicator = 1;
                }

                if (is_set) {
                    sku_mod.count = stocks_count;
                }
            }
        };

        Section.prototype.updateProductMainPhoto = function(image_id) {
            var that = this;

            if (!that.product.photos.length) { return false; }

            if (!image_id) {
                image_id = (that.product.image_id ? that.product.image_id : that.product.photos[0].id);
            }

            var photo_array = that.product.photos.filter( function(image) {
                return (image.id === image_id);
            });

            that.product.photo = (photo_array.length ? photo_array[0] : that.product.photos[0]);
        };

        Section.prototype.addFeatureValueRequest = function(request_data) {
            var that = this;

            var deferred = $.Deferred();

            $.post(that.urls["add_feature_value"], request_data, "json")
                .done( function(response) {
                    if (response.status === "ok") {
                        deferred.resolve(response.data);
                    } else {
                        deferred.reject(response.errors);
                    }
                })
                .fail( function() {
                    deferred.reject([]);
                });

            return deferred.promise();
        };

        Section.prototype.addFeatureValueToModel = function(feature, option) {
            var that = this;

            var features = [];
            features = features.concat(that.product.features);

            $.each(that.product.skus, function(sku_i, sku) {
                $.each(sku.modifications, function(sku_mod_i, sku_mod) {
                    features = features.concat(sku_mod.features_selectable);
                    features = features.concat(sku_mod.features);
                });
            });

            $.each(features, function(i, _feature) {
                var active_option = {
                    name: option.name,
                    value: option.value
                };

                if (feature.render_type === "checkbox") {
                    active_option.active = (typeof option.active === "boolean" ? option.active : false);
                }

                if (_feature.code === feature.code) {
                    _feature.options.push(active_option);
                }

                if (feature.render_type === "select") {
                    if (_feature === feature) {
                        _feature.active_option = active_option;
                    }
                }
            });
        };

        // Move/Touch события для перемещеная артикулов/модификаций

        Section.prototype.initDragAndDrop = function(vue) {
            var that = this;

            var move_class = "is-moving";

            // VARS
            var timer = 0,
                drag_data = {
                    before: null,
                    moved_mod: null,
                    moved_mod_id: null,
                    moved_mod_index: null,
                    moved_sku_index: null,
                    drop_mod: null,
                    drop_mod_id: null,
                    drop_mod_index: null,
                    drop_sku_index: null
                };

            //

            var $droparea = $("<div />", { class: "s-drop-area js-drop-area" });

            that.$wrapper.on("dragstart", ".js-modification-move-toggle", function(event) {
                start($(this).closest(".s-modification-wrapper"));

                var $modification = $(drag_data.moved_mod);
                event.originalEvent.dataTransfer.setDragImage($modification[0], 20, 20);
                $modification.addClass(move_class);
            });

            that.$wrapper.on("dragend", ".js-modification-move-toggle", function(event) {
                end();
                $(drag_data.moved_mod).removeClass(move_class);
            });

            that.$wrapper.on("dragover", ".s-modification-wrapper", function(event) {
                event.preventDefault();

                var $modification = $(this),
                    over_mod_index = $modification.data("index"),
                    over_sku_index = $modification.closest(".s-sku-section").data("index");

                if (over_sku_index === drag_data.moved_sku_index && over_mod_index === drag_data.moved_mod_index) {
                    // position not change

                } else {
                    var mouse_y = event.originalEvent.pageY,
                        mod_offset = $modification.offset(),
                        mod_height = $modification.outerHeight(),
                        mod_y = mod_offset.top,
                        x = (mouse_y - mod_y),
                        before = ( x < (mod_height/2) );

                    if (before !== drag_data.before) {
                        if (before) {
                            $modification.before($droparea);
                        } else {
                            $modification.after($droparea);
                        }
                        drag_data.before = before;
                    }
                }
            });

            that.$wrapper.on("drop", ".s-modification-wrapper", function() {
                var $modification = $(this);
                drag_data.drop_mod = $modification[0];
                drag_data.drop_mod_id = $modification.data("id");
                drag_data.drop_mod_index = $modification.data("index");
                drag_data.drop_sku_index = $modification.closest(".s-sku-section").data("index");
                drop(drag_data);
            });

            function start($modification) {
                drag_data.before = null;
                drag_data.moved_mod = $modification[0];
                drag_data.moved_mod_id = $modification.data("id");
                drag_data.moved_mod_index = $modification.data("index");
                drag_data.moved_sku_index = $modification.closest(".s-sku-section").data("index");
                drag_data.drop_mod = null;
                drag_data.drop_mod_id = null;
                drag_data.drop_mod_index = null;
                drag_data.drop_sku_index = null;
            }

            function drop(drag_data) {
                var new_index = drag_data.drop_mod_index + (drag_data.before ? 0 : 1);

                if (drag_data.moved_mod === drag_data.drop_mod) {
                    // position not change
                } else if (drag_data.moved_sku_index === drag_data.drop_sku_index && new_index === drag_data.moved_mod_index) {
                    // position not change
                } else {
                    move();
                }

                end();

                function move() {
                    var moved_sku = that.product.skus[drag_data.moved_sku_index],
                        drop_sku = that.product.skus[drag_data.drop_sku_index],
                        moved_mod = moved_sku.modifications[drag_data.moved_mod_index],
                        drop_mod = moved_sku.modifications[drag_data.drop_mod_index];

                    // remove target
                    moved_sku.modifications.splice(drag_data.moved_mod_index, 1);

                    // set target
                    var drop_index = null;
                    $.each(drop_sku.modifications, function(i, mod) {
                        if (mod === drop_mod) { drop_index = i; }
                    });
                    new_index = drop_index + (drag_data.before ? 0 : 1);

                    drop_sku.modifications.splice(new_index, 0, moved_mod);

                    drag_data = {
                        before: null,
                        moved_mod: null,
                        moved_mod_id: null,
                        moved_mod_index: null,
                        moved_sku_index: null,
                        drop_mod: null,
                        drop_mod_id: null,
                        drop_mod_index: null,
                        drop_sku_index: null
                    };
                }
            }

            function end(use_timer) {
                clearTimeout(timer);

                if (use_timer) {
                    timer = setTimeout( function() {
                        $droparea.detach();
                    }, 200);
                } else {
                    $droparea.detach();
                }
            }
        };

        Section.prototype.initTouchAndDrop = function(vue) {
            var that = this;

            var move_class = "is-moving";

            var render_timeout = 200,
                is_locked = false,
                start_offset = { top: 0, left: 0 },
                target_modification = null;

            var drag_data = {
                before: null,
                moved_mod: null,
                moved_mod_id: null,
                moved_mod_index: null,
                moved_sku_index: null,
                drop_mod: null,
                drop_mod_id: null,
                drop_mod_index: null,
                drop_sku_index: null
            };

            var $droparea = $("<div />", { class: "s-drop-area js-drop-area" });

            // Touch
            that.$wrapper[0].addEventListener("touchstart", function(event) {
                var toggle = event.target.closest(".js-modification-move-toggle");
                if (!toggle) { return; }

                event.preventDefault();

                var $modification = $(toggle).closest(".s-modification-wrapper");
                $modification.addClass(move_class);
                start($modification);

                var touch = event.touches[0];
                start_offset.left = touch.pageX;
                start_offset.top = touch.pageY;

                document.addEventListener("touchmove", onTouchMove, false);
                document.addEventListener("touchend", onTouchEnd, false);

                function onTouchMove(event) {
                    event.preventDefault();

                    var touch = event.touches[0];

                    move($modification, touch.pageX, touch.pageY);

                    if (is_locked) { return; }
                    is_locked = true;
                    setTimeout( function() { is_locked = false; }, render_timeout);

                    $modification.css("visibility", "hidden");
                    var target = document.elementFromPoint(touch.clientX, touch.clientY);
                    $modification.css("visibility", "");

                    if (!target) { return; }

                    var target_mod = target.closest(".s-modification-wrapper");
                    if (target_mod) { hover($(target_mod), $modification, touch.pageX, touch.pageY); }
                    if (target_modification !== target_mod) {
                        if (target_mod) { enter($(target_mod), $modification); }
                        if (target_modification) { leave($(target_modification), $modification); target_modification = null; }
                        target_modification = target_mod;
                    }
                }

                function onTouchEnd() {
                    if (target_modification) {
                        var $drop_mod = $(target_modification);
                        drag_data.drop_mod = $drop_mod[0];
                        drag_data.drop_mod_id = $drop_mod.data("id");
                        drag_data.drop_mod_index = $drop_mod.data("index");
                        drag_data.drop_sku_index = $drop_mod.closest(".s-sku-section").data("index");
                        drop(drag_data);
                    }

                    document.removeEventListener('touchmove', onTouchMove);
                    document.removeEventListener('touchend', onTouchEnd);
                    $modification
                        .removeClass(move_class)
                        .attr("style", "");
                }
            });

            // Mouse
            /*
            that.$wrapper.on("mousedown", ".js-modification-move-toggle2", function(event) {
                var $modification = $(this).closest(".s-modification-wrapper");
                $modification.addClass(move_class);
                start($modification);

                start_offset.left = event.originalEvent.pageX;
                start_offset.top = event.originalEvent.pageY;

                var $document = $(document);
                $document
                    .on("mousemove", onMouseMove)
                    .on("mouseup", onMouseUp);

                function onMouseMove(event) {
                    move($modification, event.originalEvent.pageX, event.originalEvent.pageY);

                    if (is_locked) { return; }
                    is_locked = true;
                    setTimeout( function() { is_locked = false; }, render_timeout);

                    $modification.css("visibility", "hidden");
                    var target = document.elementFromPoint(event.originalEvent.clientX, event.originalEvent.clientY);
                    $modification.css("visibility", "");

                    if (!target) { return; }

                    var target_mod = target.closest(".s-modification-wrapper");
                    if (target_mod) { hover($(target_mod), $modification, event.originalEvent.pageX, event.originalEvent.pageY); }
                    if (target_modification !== target_mod) {
                        if (target_mod) { enter($(target_mod), $modification); }
                        if (target_modification) { leave($(target_modification), $modification); target_modification = null; }
                        target_modification = target_mod;
                    }
                }

                function onMouseUp() {
                    if (target_modification) {
                        var $drop_mod = $(target_modification);
                        drag_data.drop_mod = $drop_mod[0];
                        drag_data.drop_mod_id = $drop_mod.data("id");
                        drag_data.drop_mod_index = $drop_mod.data("index");
                        drag_data.drop_sku_index = $drop_mod.closest(".s-sku-section").data("index");
                        drop(drag_data);
                    }

                    $document.off("mousemove", onMouseMove);
                    $document.off("mouseup", onMouseUp);
                    $modification
                        .removeClass(move_class)
                        .attr("style", "");
                }
            });
            */

            //

            function enter($target_mod, $move_mod) {
                $target_mod.addClass("is-hover");
            }

            function leave($target_mod, $move_mod) {
                $target_mod.removeClass("is-hover");
                drag_data.before = null;
                end();
            }

            function hover($target_mod, $move_mod, x, y) {
                var over_mod_index = $target_mod.data("index"),
                    over_sku_index = $target_mod.closest(".s-sku-section").data("index");

                if (over_sku_index === drag_data.moved_sku_index && over_mod_index === drag_data.moved_mod_index) {
                    // position not change

                } else {
                    var mouse_y = y,
                        mod_offset = $target_mod.offset(),
                        mod_height = $target_mod.outerHeight(),
                        delta = (y - mod_offset.top),
                        before = (delta < (mod_height / 2));

                    if (before !== drag_data.before) {
                        if (before) {
                            $target_mod.before($droparea);
                        } else {
                            $target_mod.after($droparea);
                        }
                        drag_data.before = before;
                    }
                }
            }

            function start($modification) {
                drag_data.before = null;
                drag_data.moved_mod = $modification[0];
                drag_data.moved_mod_id = $modification.data("id");
                drag_data.moved_mod_index = $modification.data("index");
                drag_data.moved_sku_index = $modification.closest(".s-sku-section").data("index");
                drag_data.drop_mod = null;
                drag_data.drop_mod_id = null;
                drag_data.drop_mod_index = null;
                drag_data.drop_sku_index = null;
            }

            function move($move_mod, x, y) {
                $move_mod.css("transform", "translate(" + (x - start_offset.left) + "px," + (y - start_offset.top) + "px)");
            }

            function drop(drag_data) {
                end();

                var new_index = drag_data.drop_mod_index + (drag_data.before ? 0 : 1);

                if (drag_data.moved_mod === drag_data.drop_mod) {
                    // position not change
                } else if (drag_data.moved_sku_index === drag_data.drop_sku_index && new_index === drag_data.moved_mod_index) {
                    // position not change
                } else {
                    move();
                }

                function move() {
                    var moved_sku = that.product.skus[drag_data.moved_sku_index],
                        drop_sku = that.product.skus[drag_data.drop_sku_index],
                        moved_mod = moved_sku.modifications[drag_data.moved_mod_index],
                        drop_mod = moved_sku.modifications[drag_data.drop_mod_index];

                    // remove target
                    moved_sku.modifications.splice(drag_data.moved_mod_index, 1);

                    // set target
                    var drop_index = null;
                    $.each(drop_sku.modifications, function(i, mod) {
                        if (mod === drop_mod) { drop_index = i; }
                    });
                    new_index = drop_index + (drag_data.before ? 0 : 1);

                    drop_sku.modifications.splice(new_index, 0, moved_mod);

                    drag_data = {
                        before: null,
                        moved_mod: null,
                        moved_mod_id: null,
                        moved_mod_index: null,
                        moved_sku_index: null,
                        drop_mod: null,
                        drop_mod_id: null,
                        drop_mod_index: null,
                        drop_sku_index: null
                    };
                }
            }

            function end() {
                $droparea.detach();
            }
        };

        // Сохранение

        Section.prototype.validate = function() {
            var that = this;

            var errors = [];

            var sku_sku_groups = getSkuSkuGroups(that.product.skus);
            $.each(that.product.skus, function(i, sku) {
                var sku_sku = (sku.sku ? sku.sku : "");
                var sku_group = sku_sku_groups[sku_sku];

                // очищаем ошибку пустого поля если она была
                if (sku.errors["sku.sku.required"]) {
                    Vue.delete(sku.errors, "sku.sku.required");
                }
                // очищаем ошибку уникального значения если она была
                if (sku.errors["sku.sku.unique"]) {
                    Vue.delete(sku.errors, "sku.sku.unique");
                }

                // Ошибка пустого значения
                if (!sku_sku.length) {
                    if (sku.modifications.length > 1) {
                        Vue.set(sku.errors, "sku.sku.required", {
                            "id": "sku.sku.required",
                            "text": that.locales["sku_required"]
                        });
                        errors.push("sku_required");
                    }
                // Значение есть
                } else {
                    // Ошибка уникального значения
                    if (sku_group.length > 1 && sku_group.indexOf(sku) >= 0) {
                        Vue.set(sku.errors, "sku.sku.unique", {
                            "id": "sku.sku.unique",
                            "text": that.locales["sku_unique_error"]
                        });
                        errors.push("sku.sku.unique");
                    }
                }
            });

            return errors;

            function getSkuSkuGroups(skus) {
                var result = {};

                $.each(skus, function(i, sku) {
                    if (!result[sku.sku]) {
                        result[sku.sku] = [];
                    }

                    if (sku.sku) {
                        result[sku.sku].push(sku);
                    } else {
                        result[""].push(sku);
                    }
                });

                return result;
            }
        };

        // Сохранение

        Section.prototype.initSave = function() {
            var that = this;

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("click", ".js-product-save", function (event) {
                event.preventDefault();

                // Останавливаем сохранение если во фронте есть ошибки
                var errors = that.validate();
                if (errors.length) {
                    var $error = $(".wa-error-text:first");
                    if ($error.length) {
                        $(window).scrollTop($error.offset().top - 100);
                    }
                    return false;
                }

                var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var $button = $(this),
                    $loading = $(loading).appendTo($button.attr("disabled", true));

                sendRequest()
                    .done( function() {
                        $.wa_shop_products.router.reload();
                    })
                    .fail( function () {
                        $button.attr("disabled", false);
                        $loading.remove();
                    });
            });

            function sendRequest() {
                var data = getProductData();

                return request(data);

                function request(data) {
                    var deferred = $.Deferred();

                    $.post(that.urls["save"], data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve(response);
                            } else {
                                deferred.reject(response.errors);
                            }
                        })
                        .fail( function() {
                            deferred.reject();
                        });

                    return deferred.promise();
                }
            }

            function getProductData() {
                var data = [
                    {
                        "name": "product[id]",
                        "value": that.product.id
                    },
                    {
                        "name": "product[sku_id]",
                        "value": that.product.sku_id
                    },
                    {
                        "name": "product[sku_type]",
                        "value": that.product.sku_type
                    },
                    {
                        "name": "product[currency]",
                        "value": that.product.currency
                    }
                ];

                setBadge();

                setSKUS();

                setFeatures(that.product.features, "product[features]");

                getFeaturesSelectable();

                return data;

                function setSKUS() {
                    $.each(that.product.skus, function(i, sku) {
                        $.each(sku.modifications, function(i, sku_mod) {
                            var prefix = "product[skus][" + sku_mod.id + "]";
                            data.push({
                                name: prefix + "[name]",
                                value: sku_mod.name
                            });
                            data.push({
                                name: prefix + "[sku]",
                                value: sku_mod.sku
                            });
                            data.push({
                                name: prefix + "[status]",
                                value: sku_mod.status
                            });
                            data.push({
                                name: prefix + "[available]",
                                value: (sku_mod.available ? 1 : 0)
                            });
                            data.push({
                                name: prefix + "[image_id]",
                                value: sku_mod.image_id
                            });
                            data.push({
                                name: prefix + "[price]",
                                value: sku_mod.price
                            });
                            data.push({
                                name: prefix + "[compare_price]",
                                value: sku_mod.compare_price
                            });
                            data.push({
                                name: prefix + "[purchase_price]",
                                value: sku_mod.purchase_price
                            });

                            setFeatures(sku_mod.features_selectable, prefix + "[features]");
                            setFeatures(sku_mod.features, prefix + "[features]");

                            setStocks(sku_mod, prefix);
                        });
                    });

                    function setStocks(sku_mod, prefix) {
                        var stocks_data = [];

                        var is_stocks_mode = false;

                        $.each(sku_mod.stock, function(stock_id, stock_value) {
                            var value = parseFloat(stock_value);
                            if (value >= 0) {
                                is_stocks_mode = true;
                            } else {
                                value = "";
                            }

                            stocks_data.push({
                                name: prefix + "[stock][" + stock_id + "]",
                                value: value
                            });
                        });

                        if (!is_stocks_mode) {
                            stocks_data = [{
                                name: prefix + "[stock][0]",
                                value: sku_mod.count
                            }];
                        }

                        data = data.concat(stocks_data);
                    }
                }

                function setBadge() {
                    var value = "";

                    if (typeof that.product.badge_id === "string") {
                        var active_badges = that.product.badges.filter( function(badge) {
                            return (badge.id === that.product.badge_id);
                        });

                        if (active_badges.length) {
                            var active_badge = active_badges[0];
                            value = (active_badge.id === "" ? active_badge.code : active_badge.id);
                        }
                    }

                    data.push({
                        name: "product[badge]",
                        value: value
                    });
                }

                function setFeatures(features, root_prefix) {
                    if (!features.length) { return false; }

                    $.each(features, function(i, feature) {
                        var prefix = root_prefix + "[" + feature.code + "]";

                        switch (feature.render_type) {
                            case "field":
                                $.each(feature.options, function(i, option) {
                                    var local_prefix = root_prefix + "[" + feature.code + "]";
                                    if (feature.options.length > 1) {
                                        local_prefix = root_prefix + "[" + feature.code + "." + i + "]";
                                    }
                                    data.push({
                                        name: local_prefix + "[value]",
                                        value: option["value"]
                                    });
                                });

                                if (feature.active_unit) {
                                    data.push({
                                        name: root_prefix + "[" + feature.code + (feature.options.length > 1 ? ".0" : "") + "][unit]",
                                        value: feature.active_unit["value"]
                                    });
                                }
                                break;
                            case "select":
                                data.push({
                                    name: prefix,
                                    value: feature.active_option["value"]
                                });
                                break;
                            case "checkbox":
                                $.each(feature.options, function(i, option) {
                                    if (option.active) {
                                        data.push({
                                            name: prefix + "[]",
                                            value: option["value"]
                                        });
                                    }
                                });
                                break;
                            case "textarea":
                                data.push({
                                    name: prefix,
                                    value: feature["value"]
                                });
                                break;
                            case "range":
                                data.push({
                                    name: prefix + "[value][begin]",
                                    value: feature.options[0]["value"]
                                });
                                data.push({
                                    name: prefix + "[value][end]",
                                    value: feature.options[1]["value"]
                                });
                                break;
                            case "range.date":
                                data.push({
                                    name: prefix + "[value][begin]",
                                    value: feature.options[0]["value"]
                                });
                                data.push({
                                    name: prefix + "[value][end]",
                                    value: feature.options[1]["value"]
                                });
                                break;
                            case "range.volume":
                                data.push({
                                    name: prefix + "[value][begin]",
                                    value: feature.options[0]["value"]
                                });
                                data.push({
                                    name: prefix + "[value][end]",
                                    value: feature.options[1]["value"]
                                });
                                if (feature.active_unit) {
                                    data.push({
                                        name: prefix + "[unit]",
                                        value: feature.active_unit["value"]
                                    });
                                }
                                break;
                            case "field.date":
                                data.push({
                                    name: prefix,
                                    value: feature.options[0]["value"]
                                });
                                break;
                            case "color":
                                data.push({
                                    name: prefix + "[value]",
                                    value: feature.options[0]["value"]
                                });
                                data.push({
                                    name: prefix + "[code]",
                                    value: feature.options[0]["code"]
                                });
                                break;
                            default:
                                break;
                        }
                    });
                }

                function getFeaturesSelectable() {
                    var count_added = 0;
                    $.each(that.selectable_features, function(i, feature) {
                        if (feature.active) {
                            count_added++;
                            data.push({
                                name: "product[features_selectable][]",
                                value: feature.id
                            });
                        }
                    });

                    if (count_added <= 0) {
                        // empty string means empty array
                        data.push({
                            name: "product[features_selectable]",
                            value: ''
                        });
                    }
                }
            }
        };

        // Различные диалоги

        Section.prototype.showDeleteConfirm = function(sku, sku_mod) {
            var that = this;

            return showConfirm();

            function showConfirm() {
                var deferred = $.Deferred();
                var is_success = false;

                var data = [];
                data.push({
                    name: "product_id",
                    value: that.product.id
                });

                var exist_count = 0;

                if (sku_mod) {
                    data.push({
                        name: "sku_id",
                        value: sku_mod.id
                    });
                    if (sku_mod.id > 0) { exist_count += 1; }

                } else {
                    $.each(sku.modifications, function(i, sku_mod) {
                        data.push({
                            name: "sku_id[]",
                            value: sku_mod.id
                        });
                        if (sku_mod.id > 0) { exist_count += 1; }
                    });
                }

                if (exist_count > 0) {
                    $.post(that.urls["sku_delete_dialog"], data, "json")
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
                } else {
                    deferred.resolve();
                }

                return deferred.promise();
            }
        };

        Section.prototype.initMassSkuGenerationDialog = function($dialog, dialog) {
            var that = this;

            var root_vue_model = dialog.options.vue_model;

            var $vue_section = $dialog.find("#vue-generator-section");

            var timer = 0;

            new Vue({
                el: $vue_section[0],
                data: {
                    use_values: true,
                    prices: {
                        price: null,
                        compare_price: null,
                        purchase_price: null
                    },
                    features: getFeatures(root_vue_model),
                    skus: [],
                    modifications: [],
                    currency: that.product.currency,
                    currencies: that.currencies
                },
                delimiters: ['{ { ', ' } }'],
                methods: {
                    getActiveFeatures: function(use_mod) {
                        var self = this;
                        var result = [];

                        $.each(self.features, function(i, feature) {
                            if (feature.active) {
                                if (typeof use_mod !== "boolean") {
                                    result.push(feature);
                                } else if (feature.use_mod === use_mod) {
                                    result.push(feature);
                                }
                            }
                        });

                        return result;
                    },
                    getCount: function(return_mods) {
                        var self = this;

                        var active_features = self.getActiveFeatures(),
                            sku_features = self.getActiveFeatures(false),
                            mods_features = self.getActiveFeatures(true),
                            sku_count = 0,
                            mods_count = 0;

                        $.each(sku_features, function(i, feature) {
                            var active_options_count = 0;

                            feature.options.forEach( function(option) {
                                if (option.active && option.value.length) { active_options_count += 1; }
                            });

                            if (active_options_count) {
                                sku_count = ( sku_count ? sku_count * active_options_count : active_options_count);
                            }
                        });

                        $.each(mods_features, function(i, feature) {
                            var active_options_count = 0;

                            feature.options.forEach( function(option) {
                                if (option.active && option.value.length) { active_options_count += 1; }
                            });

                            if (active_options_count) {
                                mods_count = (mods_count ? mods_count * active_options_count : active_options_count);
                            }
                        });

                        if (sku_count > 0 && mods_count === 0) {
                            mods_count = 1;
                        } else if (sku_count === 0 && mods_count > 0) {
                            sku_count = 1;
                        }

                        return (return_mods ? mods_count : sku_count);
                    },
                    getLocale: function(count, locales) {
                        if (count === 1) {
                            return locales[0];
                        } else {
                            return locales[1];
                        }
                    },
                    startGenerator: function() {
                        var self = this;
                        dialog.options.onSuccess(self.features);
                        dialog.close();
                    },
                    onFeatureUpdated: function() {
                        dialog.resize();
                    }
                },
                created: function () {
                    $vue_section.css("visibility", "");
                },
                updated: function() {
                    var self = this;

                    dialog.resize();
                },
                mounted: function () {
                    var self = this;

                    initDragAndDrop(self);

                    dialog.resize();

                    var $dropdown = $(self.$el).find(".js-features-dropdown");
                    if ($dropdown.length) {
                        initFeaturesDropdown($dropdown);
                    }

                    function initFeaturesDropdown($dropdown) {
                        $dropdown.waDropdown({
                            hover: false
                        });

                        var dropdown = $dropdown.data("dropdown");

                        $dropdown.on("click", ".js-apply-button", function(event) {
                            event.preventDefault();

                            self.features.forEach( function(feature) {
                                feature.active = feature.dropdown_active;
                            });

                            dropdown.hide();
                        });

                        $dropdown.on("click", ".js-close-button", function(event) {
                            event.preventDefault();
                            dropdown.hide();
                        });
                    }
                }
            });

            function getFeatures(root_vue_model) {
                var all_features_object = $.wa.construct( root_vue_model.product.features, "id"),
                    features = clone(root_vue_model.selectable_features),
                    result = [];

                $.each(features, function(i, feature) {
                    if (all_features_object[feature.id]) {
                        feature.options = clone(all_features_object[feature.id].options);
                        feature.options.forEach( function(option) {
                            option.active = false;
                        });
                        feature.code = all_features_object[feature.id].code;
                        feature.use_mod = true;
                        feature.expanded = false;
                        feature.dropdown_active = (typeof feature.active === "boolean" ? feature.active : false);
                        result.push(feature);
                    }
                });

                return result;
            }

            function initDragAndDrop(vue) {
                var that = this;

                var move_class = "is-moving",
                    over_class = "is-over";

                // VARS
                var timer = 0,
                    drag_data = null;

                //

                var $droparea = $("<div />", { class: "s-drop-area js-drop-area" });

                $dialog.on("dragstart", ".js-feature-move-toggle", function(event) {
                    var $move = $(this).closest(".s-feature-wrapper");
                    event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);
                    $move.addClass(move_class);
                    drag_data = {
                        $move: $move
                    };
                });

                $dialog.on("dragover", function(event) {
                    event.preventDefault();

                    var target = event.target.closest(".js-drop-area");
                    if (!target) {
                        removeHighlight();
                    } else {
                        var $over = $(target);
                        if (drag_data.$over && drag_data.$over[0] !== $over[0]) {
                            removeHighlight();
                        }
                        $over.addClass(over_class);
                        drag_data.$over = $over;
                    }
                });

                $dialog.on("dragend", function(event) {
                    drag_data.$move.removeClass(move_class);
                    drag_data = null;
                });

                $dialog.on("drop", ".js-drop-area", function(event) {
                    removeHighlight();

                    drag_data.$drop = $(this);

                    var type = drag_data.$drop.closest(".s-features-section").data("type");
                    drag_data.use_mod = (type === "mod");

                    var move_feature_code = drag_data.$move.attr("data-feature-code");
                    drag_data.move_feature = null;
                    drag_data.move_feature_index = 0;

                    var drop_feature_code = drag_data.$drop.attr("data-feature-code");
                    drag_data.drop_feature = null;
                    drag_data.drop_feature_index = 0;

                    $.each(vue.features, function(i, feature) {
                        if (feature.code === move_feature_code) {
                            drag_data.move_feature = feature;
                            drag_data.move_feature_index = i;
                        }
                        if (drop_feature_code && feature.code === drop_feature_code) {
                            drag_data.drop_feature = feature;
                            drag_data.drop_feature_index = i;
                        }
                    });

                    drag_data.drop_before = true;
                    if (drag_data.drop_feature) {
                        var top = event.originalEvent.pageY;
                        var offset = drag_data.$drop.offset();
                        var height = drag_data.$drop.outerHeight();
                        var after = (top - offset.top > (height/2));
                        drag_data.drop_before = !after;
                    }

                    onDrop(drag_data);
                });

                function removeHighlight() {
                    if (drag_data.$over) {
                        drag_data.$over.removeClass(over_class);
                        drag_data.$over = null;
                    }
                }

                function onDrop(drag_data) {
                    // drop on feature
                    if (drag_data.drop_feature && drag_data.drop_feature !== drag_data.move_feature) {
                        // remove target
                        vue.features.splice(drag_data.move_feature_index, 1);
                        //
                        var drop_index = null;
                        $.each(vue.features, function(i, feature) {
                            if (feature === drag_data.drop_feature) { drop_index = i; }
                        });
                        var new_index = drop_index + (drag_data.drop_before ? 0 : 1);
                        // add
                        vue.features.splice(new_index, 0, drag_data.move_feature);
                    }

                    drag_data.move_feature.use_mod = drag_data.use_mod;
                }
            }
        };

        Section.prototype.initPhotoManagerDialog = function($dialog, dialog, sku_mod) {
            var that = this;

            var $vue_section = $dialog.find("#vue-photo-manager-section");

            var photo_id = sku_mod.image_id,
                active_photo = null,
                photos = clone(that.product.photos);

            photos.forEach( function(photo) {
                photo = formatPhoto(photo);
                if (photo_id && photo_id === photo.id) { active_photo = photo; }
            });

            if (!active_photo && photos.length) {
                active_photo = photos[0];
            }

            new Vue({
                el: $vue_section[0],
                data: {
                    photo_id: photo_id,
                    photos: photos,
                    active_photo: active_photo,
                    files: [],
                    errors: []
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-loading-file": {
                        props: ['file'],
                        template: '<div class="vue-component-loading-file"><div class="wa-progressbar" style="display: inline-block;"></div></div>',
                        mounted: function() {
                            var self = this;

                            var $bar = $(self.$el).find(".wa-progressbar").waProgressbar({ type: "circle", "stroke-width": 4.8, display_text: false }),
                                instance = $bar.data("progressbar");

                            loadPhoto(self.file)
                                .done( function(response) {
                                    $.each(response.files, function(i, photo) {
                                        // remove loading photo
                                        self.$emit("photo_added", {
                                            file: self.file,
                                            photo: photo
                                        });
                                    });
                                });

                            function loadPhoto(file) {
                                var formData = new FormData();

                                formData.append("product_id", that.product.id);
                                formData.append("files", file);

                                // Ajax request
                                return $.ajax({
                                    xhr: function() {
                                        var xhr = new window.XMLHttpRequest();
                                        xhr.upload.addEventListener("progress", function(event){
                                            if (event.lengthComputable) {
                                                var percent = parseInt( (event.loaded / event.total) * 100 );
                                                instance.set({ percentage: percent });
                                            }
                                        }, false);
                                        return xhr;
                                    },
                                    url: that.urls["add_product_image"],
                                    data: formData,
                                    cache: false,
                                    contentType: false,
                                    processData: false,
                                    type: 'POST'
                                });
                            }
                        }
                    }
                },
                methods: {
                    setPhoto: function(photo) {
                        var self = this;
                        self.active_photo = photo;
                    },
                    useChanges: function() {
                        var self = this;
                        if (self.active_photo) {
                            dialog.options.onSuccess(self.active_photo.id);
                            dialog.close();
                        }
                    },
                    showDescriptionForm: function(photo) {
                        photo.expanded = true;
                    },
                    changeDescription: function(event, photo) {
                        var $button = $(event.currentTarget);
                        $button.attr("disabled", true);

                        var href = that.urls["change_image_description"],
                            data = {
                                "id": photo.id,
                                "data[description]": photo.description
                            }

                        $.post(href, data, "json")
                            .always( function() {
                                $button.attr("disabled", false);
                            })
                            .done( function() {
                                photo.description_before = photo.description;
                                photo.expanded = false;
                            });
                    },
                    revertDescription: function(photo) {
                        photo.expanded = false;
                        photo.description = photo.description_before;
                    },
                    onAreaOver: function(event) {
                        var $area = $(event.currentTarget);

                        var active_class = "is-over";

                        var timer = $area.data("timer");
                        if (typeof timer === "number") { clearTimeout(timer); }

                        $area.addClass(active_class);
                        timer = setTimeout( clear, 100);
                        $area.data("timer", timer);

                        function clear() {
                            $area.removeClass(active_class);
                        }
                    },
                    onAreaDrop: function(event) {
                        var self = this,
                            files = event.dataTransfer.files;

                        if (files.length) {
                            $.each(files, function(i, file) {
                                self.loadFile(file);
                            });
                        }
                    },
                    onAreaChange: function(event) {
                        var self = this,
                            files = event.target.files;

                        if (files.length) {
                            $.each(files, function(i, file) {
                                self.loadFile(file);
                            });
                        }

                        // clear
                        $(event.target).val("");
                    },
                    onAddedPhoto: function(data) {
                        var self = this;

                        var photo = formatPhoto(data.photo);

                        // удаляем UI загрузки
                        var index = self.files.indexOf(data.file);
                        if (index >= 0) { self.files.splice(index, 1); }

                        // Добавляем фотку в модели данных (диалог + страница)
                        that.product.photos.push(photo);
                        self.photos.unshift(photo);

                        self.setPhoto(photo);
                    },

                    //
                    loadFile: function(file) {
                        var self = this;

                        var file_size = file.size,
                            image_type = /^image\/(png|jpe?g|gif)$/,
                            is_image_type = (file.type.match(image_type)),
                            is_image = false;

                        var name_array = file.name.split("."),
                            ext = name_array[name_array.length - 1];

                        ext = ext.toLowerCase();

                        var white_list = ["png", "jpg", "jpeg", "gif"];
                        if (is_image_type && white_list.indexOf(ext) >= 0) {
                            is_image = true;
                        }

                        if (!is_image) {
                            renderError({ id: "not_image", text: "ERROR: NOT IMAGE" });
                        } else if (file_size >= that.max_file_size) {
                            renderError({ id: "big_size", text: "ERROR: big file size" });
                        } else if (file_size >= that.max_post_size) {
                            renderError({ id: "big_post", text: "ERROR: big POST file size" });
                        } else {
                            self.files.push(file);
                        }

                        function renderError(error) {
                            self.errors.push(error);

                            setTimeout( function() {
                                var index = null;
                                $.each(self.errors, function(i, _error) {
                                    if (_error === error) { index = i; return false; }
                                });
                                if (typeof index === "number") { self.errors.splice(index, 1); }
                            }, 2000);
                        }
                    }
                },
                created: function () {
                    $vue_section.css("visibility", "");
                },
                updated: function() {
                    var self = this;

                    $dialog.find("textarea.s-description-field").each( function() {
                        that.toggleHeight( $(this) );
                    });

                    var $content = $dialog.find(".dialog-content"),
                        content_top = $content.scrollTop();

                    dialog.resize();
                    $content.scrollTop(content_top);
                },
                mounted: function () {
                    var self = this;

                    dialog.resize();
                }
            });

            function formatPhoto(photo) {
                photo.expanded = false;
                if (typeof photo.description !== "string") { photo.description = ""; }
                photo.description_before = photo.description;
                return photo;
            }
        };

        Section.prototype.initChangeFeatureValuesDialog = function(vue_model, feature) {
            var that = this;

            $.waDialog({
                html: that.templates["dialog_feature_select"],
                options: {
                    feature: clone(feature),
                    onSuccess: function(changed_options) {
                        $.each(changed_options, function(i, option) {
                            feature.options[i].active = option.active;
                        });

                        that.$wrapper.trigger("change");
                    }
                },
                onOpen: initDialog
            });

            function initDialog($dialog, dialog) {
                var $section = $dialog.find("#vue-features-wrapper");

                new Vue({
                    el: $section[0],
                    data: {
                        feature: dialog.options.feature
                    },
                    delimiters: ['{ { ', ' } }'],
                    methods: {
                        onFeatureValueAdded: function(option) {
                            var self = this;
                            self.feature.options.push({
                                name: option.name,
                                value: option.value,
                                active: true
                            });
                        },
                        success: function() {
                            dialog.options.onSuccess(dialog.options.feature.options);
                            dialog.close();
                        },
                        close: function() {
                            dialog.close();
                        }
                    },
                    created: function () {
                        $section.css("visibility", "");
                    },
                    mounted: function () {
                        dialog.resize();

                        initSearch();

                        function initSearch() {
                            var timer = 0;

                            var $list = $dialog.find(".js-options-list");
                            $dialog.on("keyup change", ".js-filter-field", function(event) {
                                var $field = $(this);

                                clearTimeout(timer);
                                timer = setTimeout( function() {
                                    update( $field.val().toLowerCase() );
                                }, 100);
                            });

                            function update(value) {
                                $list.find(".js-option-wrapper .js-name").each( function() {
                                    var $text = $(this),
                                        $item = $text.closest(".js-option-wrapper"),
                                        is_good = ($text.text().toLowerCase().indexOf(value) >= 0);

                                    if (value.length) {
                                        if (is_good) {
                                            $item.show();
                                        } else {
                                            $item.hide();
                                        }
                                    } else {
                                        $item.show();
                                    }
                                });

                                dialog.resize();
                            }
                        }
                    }
                });
            }
        }

        // Прочее

        Section.prototype.highlight = function(type, data) {
            var that = this;

            var highlight_class = "is-highlighted";

            var time = 3000;

            switch (type) {
                case "sku":
                    $.each(data.sku.modifications, function(i, sku_mod) {
                        that.highlight("sku_mod", { sku_mod: sku_mod });
                    });

                    Vue.set(data.sku, "highlight", true);
                    setTimeout( function() {
                        Vue.set(data.sku, "highlight", false);
                    }, time);

                    if (data.focus) {
                        setTimeout( function() {
                            var $sku = that.$wrapper.find(".s-sku-section[data-index='" + that.product.skus.indexOf(data.sku) + "']");
                            if ($sku.length) { scrollTo($sku); }
                        }, 100);
                    }

                    break;
                case "sku_mod":
                    Vue.set(data.sku_mod, "highlight", true);
                    setTimeout( function() {
                        Vue.set(data.sku_mod, "highlight", false);
                    }, time);

                    if (data.focus) {
                        setTimeout( function() {
                            var $sku_mod = that.$wrapper.find(".s-modification-wrapper[data-id='" + data.sku_mod.id + "']");
                            if ($sku_mod.length) { scrollTo($sku_mod); }
                        }, 100);
                    }
                    break;
                default:
                    break;
            }

            function scrollTo($target) {
                var top = $target.offset().top,
                    shift = 100;

                top = (top - shift > 0 ? top - shift : 0);

                $(window).scrollTop(top);
            }
        };

        Section.prototype.toggleHeight = function($textarea) {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        };

        return Section;

        function clone(data) {
            return JSON.parse(JSON.stringify(data));
        }

    })($);

    $.wa_shop_products.init.initProductSkuSection = function(options) {
        return new Section(options);
    };

})(jQuery);