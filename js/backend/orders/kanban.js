var Kanban = (($) => {

    let filterParams = null;
    let filterParamsStr = null;

    class Column {
        constructor(el) {
            this.$list = $(el);
            this.$listFooter = this.$list.find(".s-kanban__list__body__footer");
            this.$loader = this.$listFooter.find(".js-kanban-spinner");
            this.statusId = this.$list.data("kanban-list-status-id");
            this.lastOrderId = this.$list.find("[data-order-id]:last").data('order-id') || 0;
            this.listLength = this.$list.find("[data-order-id]").length;

            // pagination
            this.total = this.$list.data("kanban-list-total");
        }

        observe () {
            this.intersectionObserver = new IntersectionObserver((entries) => {

                if (entries[0].intersectionRatio <= 0) return;
                if (this.listLength < this.total) {
                    this.fetch();
                }
            });
            this.intersectionObserver.observe(this.$listFooter.get(0));
        }

        fetch () {

            this.$loader.show();

            $.getJSON(this.buildLoadListUrl(this.lastOrderId))
                .done(response => {
                    if (response.data.count) {
                        response.data.orders.forEach(order => this.$listFooter.before(Column.orderTmpl(order)));
                    }

                    this.listLength = this.$list.find("[data-order-id]").length;
                    this.lastOrderId = this.$list.find("[data-order-id]:last").data('order-id') || 0;
                })
                .always(() => {
                    this.$loader.hide();
                });
        }

        static orderTmpl(order) {
            return `<div data-order-id="${order.id}" class="order_ s-kanban__list__body__item custom-p-8 custom-mb-8 blank">
                    <div class="flexbox">
                        <div class="s-kanban-item-title custom-mr-8">
                            <span class="userpic userpic48 ${(order.state_id == 'new') ? 'highlighted' : ''} bold" title="">
                                <img src="${order.contact.photo_50x50}" alt="">
                            </span>
                        </div>
                        <div class="flexbox vertical s-kanban-item-body wide">
                            <p ${(order.style) ? 'style="' + order.style + '"' : ''} class="flexbox full-width custom-m-0">
                                <a ${(order.style) ? 'style="' + order.style + '"' : ''} class="custom-mr-8" href="?action=orders#/order/${order.id}/">${order.id_str}</a>
                                <span>${order.total_str}</span>
                            </p>
                            <p class="custom-m-0">${order.contact.name}</p>
                            <p class="hint custom-m-0">${order.create_datetime_str}</p>
                        </div>
                    </div>


                    <div class="flexbox vertical s-kanban-item-body wide">
                        <a href="?action=orders#/order/${order.id}/"><i class="fas fa-truck"></i> ${order.shipping_name}</a>
                        ${(order.courier_name) ? '<a href="?action=orders#/order/' + order.id + '/" class="custom-mb-8"><span class="gray">' + order.courier_name + order.shipping_interval + '</span></a>' : ''}
                        <a href="?action=orders#/order/${order.id}/"><i class="fas fa-money-check-alt"></i> ${order.payment_name}</a>
                    </div>
                </div>`;
        }

        buildLoadListUrl(id, lt, counters) {

            const url = `?module=orders
                    &action=loadList
                    &state_id=${this.statusId}
                    &id=${id}${(lt ? '&lt=1' : '')}${(counters ? '&counters=1' : '')}
                    &view=kanban
                    &sort[0]=0
                    &sort[1]=desc`;

            return url.replace(/\s+/g, '');
        }
    }

    const addLazyLoad = (cols) => {
        cols.each((i, list) => new Column(list).observe());
    };

    const addSortable = (cols, sttr, locale) => {

        /**
         * Dragging start
         */

        // order_can_be_moved[order_id][status_id] = bool
        const order_can_be_moved = {};

        var xhr = null;

        cols.each((i, list) => {
            new Sortable(list, {
                group: {
                    name: "statuses",
                    pull: "clone",
                    put: true
                },
                animation: 150,
                sort: false,
                delay: 1500,
                delayOnTouchOnly: true,

                // Called on start of dragging. Asks server which columns given order_id can be moved into.
                onStart: (evt) => {
                    const order_id = $(evt.item).data('order-id');
                    xhr = $.post('?module=orders&action=availableActions', { id: order_id }, 'json');
                },

                // Called when user drops order into another column.
                // Sortable is set up to put a clone of original order element in both columns at this point.
                // onAdd() waits until
                onAdd: (evt) => {

                    $.when().then(() => {
                        // Can any order theoretically be moved between given columns?
                        const order_id = $(evt.item).data('order-id');
                        const from_state_id = $(evt.from).data('kanban-list-status-id');
                        const to_state_id = $(evt.to).data('kanban-list-status-id');
                        if (!sttr[from_state_id] || !sttr[from_state_id][to_state_id]) {
                            $.shop.notification({
                                class: 'danger',
                                timeout: 3000,
                                content: locale.no_action_for_states
                            });
                            return false;
                        }

                        // Can this particular order be moved between given columns?
                        return xhr.then((r) => {
                            const state_data = r.data[to_state_id];
                            if (state_data && !state_data.use_form) {
                                // All is fine, perform given action.
                                return $.post('?module=workflow&action=perform', {
                                    id: order_id,
                                    action_id: state_data.action_id
                                }, 'json').then(function(r) {
                                    return true;
                                });
                            } else {
                                $.shop.notification({
                                    class: 'danger',
                                    timeout: 3000,
                                    content: locale.action_requires_user_input
                                });
                                return false;
                            }
                        });
                    }).then((all_fine) => {
                        // Sortable is set up to put a copy of original order element in both columns.
                        // We have to delete one or the other depending on whether operation is successfull.
                        if (all_fine) {
                            $(evt.clone).remove(); // clone is in the initial column
                        } else {
                            $(evt.item).remove(); // item is in the final column
                        }
                    });
                },
            });
        });
    };

    const defineSortable = () => {
        const dfd = $.Deferred();

        if (typeof Sortable === "undefined") {
            const $script = $("#wa-header-js"),
                path = $script.attr('src').replace(/wa-content\/js\/jquery-wa\/wa.header.js.*$/, '');

            const urls = [
                "wa-content/js/sortable/sortable.min.js",
                "wa-content/js/sortable/jquery-sortable.min.js",
            ];

            const sortableDeferred = urls.reduce((dfd, url) => {
                return dfd.then(() => {
                    return $.ajax({
                        cache: true,
                        dataType: "script",
                        url: path + url
                    });
                });
            }, $.Deferred().resolve());

            sortableDeferred.done(() => {
                dfd.resolve();
            });

        } else {
            dfd.resolve();
        }

        return dfd.promise();
    };

    const setFilterParams = () => {

        filterParams = this.options.filter_params
        filterParamsStr = this.options.filter_params_str

    };

    const setWidth = () => {
        const $fix_max_width = $('.js-fix-max-width');
        const $app_sidebar= $('#js-app-sidebar');

        maxWidth();

        $(document).on('wa_toggle_products_page_sidebar', (e, is_pinned) => maxWidth(is_pinned));

        function maxWidth(is_pinned) {
            if (!is_pinned) {
                is_pinned = $app_sidebar.hasClass('is-pinned')
            }

            if (is_pinned) {
                $fix_max_width.css('max-width', 'calc(100vw - 18rem)');
            }else{
                $fix_max_width.css('max-width', 'calc(100vw - 7rem)');
            }
        }
    }

    const init = options => {

        this.options = options;

        setFilterParams();
        setWidth();

        $.when(defineSortable()).then(() => {
            const $kanbanCols = $("[data-kanban-list-status-id]");
            addSortable($kanbanCols, options.state_transitions, options.locale);
            addLazyLoad($kanbanCols);
        });
    };

    return { init };
})(jQuery);

window.Kanban = Kanban;
