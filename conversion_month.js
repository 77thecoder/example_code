// month 3d

$(document).ready(function() {
    $('#table-month').treetable({expandable: true});

    // клик по году
    $("div#filter-nvbs-month div#years-months ul#years li").one('click', function () {
        data = {};
        data.year = this.id;
        data.month = $("div#report-month ul#months li.active").attr("id");
        console.log("год " + data.year);
        console.log("месяц " + data.month);
        filter(data);
    });

    // клик по месяцу
    $("div#filter-nvbs-month ul#months li").one('click', function () {
        var data = {};
        data.month = this.id;
        data.year = $("div#report-month div#years-months ul#years li.active").attr("id");
        console.log("год " + data.year);
        console.log("месяц " + data.month);
        filter(data);
    });

    function filter(data) {
        console.log(data);
        $.ajax({
            type: "POST",
            url: "/conversion/getReport",
            data: {
                data: data
            },
            beforeSend: function() {
                $('.parent_popup').show();
                $('.ajax_loader').show();
            },
            success: function(html) {
                $("div#report-month").html(html);
                $('.parent_popup').hide();
                $('.ajax_loader').hide();
            }
        });
    }

    /**
     * Клик по статистике
     */
    $("div#report-month table#table-month img.stat_ico.month").click(function(){
        data = {};
        data.year = $("div#filter-nvbs-month div#years-months ul#years li.active").attr("id");
        data.month = $("div#filter-nvbs-month ul#months li.active").attr("id");
        data.attr = $(this).data();

        $.ajax({
            type: "POST",
            url: "/conversion/viewStats",
            data: {
                'data': data
            },
            beforeSend: function (){
                $('.parent_popup').show();
                $('.ajax_loader').show();
            },
            success: function (response){
                var stats = [];
                stats['period'] = [];
                stats['value'] = [];
                var json = JSON.parse(response);

                json.forEach(function(item, i){
                    if (item.period === "0") {
                        return true;
                    }
                    stats['period'][i] = item.period;
                    stats['value'][i] = item.kpi;
                });

                var dataset = {};
                dataset.json = stats;
                dataset.xaxis = {};
                dataset.yaxis = {};
                dataset.xaxis.period = "months";
                dataset.element = ".demo-placeholder";
                dataset.viewTooltip = true;
                dataset.xaxis.timeformat = "%m";
                $("div#modal_charts").show();
                var locality = data['attr']['region'];

                if (data['attr']['branch'] !== undefined) {
                    locality = locality + " / " + data['attr']['branch'];
                }

                if (data['attr']['city'] !== undefined) {
                    locality = locality + " / " + data['attr']['city'];
                }

                $("div#modal_charts .modal-title").html("<h4>Статистика по месяцам <small>" + locality + "</small></h4>");
                flotcharts(dataset);
            },
            complete: function(){
                $('.ajax_loader').hide();
            }
        });
    });

    /**
     * клик по отчету по участкам
     */
    $("button#report-rgos").click(function(){
        var data = {};
        data.year = $("div#filter-nvbs-month div#years-months ul#years li.active").attr("id");
        data.month = $("div#filter-nvbs-month ul#months li.active").attr("id");
        data.email = $("#user-info").data('email');
        console.log(data);
        $.ajax({
            type: 'POST',
            url: '/conversion/reportRgos',
            data: {
                data: data
            },
            beforeSend: function() {
                // ajaxLoader('show');
                alert("Подготовка отчета займет некоторое время. Отчет будет отправлен на почтовый ящик " + data.email + ". Для продолжения работы нажмите кнопку.");
            },
            success: function(){

            },
            complete: function(){
                // ajaxLoader('hide');
            }
        });
    });

    /**
     * клик по участок не указан
     */
    $("div#report-month a#area-month").click(function(){
        var data = $(this).data();
        var year = $("div#report-month div#years-months ul#years li.active").attr("id");
        var month = $("div#report-month ul#months li.active").attr("id");
        $.ajax({
            type: 'POST',
            url: '/conversion/viewNoArea',
            data: {
                period: 'month',
                value: month,
                year: year,
                data: data,
                email: $("a#user-info").data('email')
            },
            beforeSend: function(){
                // ajaxLoader('show');
                alert("Подготовка отчета займет некоторое время. Отчет будет отправлен на почтовый ящик " + $("a#user-info").data('email') + ". Для продолжения работы нажмите кнопку.");
            },
            success: function(response){
                alert("Файл выслан на вашу почту " + $("a#user-info").data('email') + ". Следуйте инструкции в письме.");
            },
            complete: function(){
                ajaxLoader('hide');
            }
        });
    });

    /**
     * клик по неподключенным
     */
    $("div#report-month table#table-month a.not-connected").click(function(){
        console.log($(this).data());
        var attr = $(this).data();
        data = {};
        data.attr = attr;
        data.year = $("div#filter-nvbs-month div#years-months ul#years li.active").attr('id');
        data.month = $("div#filter-nvbs-month ul#months li.active").attr("id");
        data.email = $("#user-info").data('email');
        $.ajax({
            type: 'POST',
            url: '/conversion/viewNotConnected',
            data: {
                data: data
            },
            beforeSend: function(){
                // ajaxLoader('show');
                alert("Подготовка отчета займет некоторое время. Отчет будет отправлен на почтовый ящик " + data.email + ". Для продолжения работы нажмите кнопку.");
            },
            success: function(response){
                $(".modal-title").html("Список неподключенных заявок");
                $("div#modal div.modal-body").html(response);
                $('#table-tickets-not-connected').treetable({expandable: true});
                $("div#modal div.modal-footer #add-btn").html('<button type="button" class="btn btn-default" id="download-in-excel" data-dismiss="modal">Выгрузить в Excel</button>')
                $("div#modal").show();
                // клик по кнопке показать комментарии
                viewComments();
                // клик по кнопке выгрузить в эксель
                downloadInExcel(data);
            },
            complete: function(){
                ajaxLoader('hide');
            }

        });
    });

    // клик по кнопке ПОКАЗАТЬ КОММЕНТЫ
    function viewComments() {
        $("button#ticket-comments").click(function () {
            data = $(this).data();
            ticket = data.ticket;
            $.ajax({
                type: 'POST',
                url: '/conversion/getCommentsTicket',
                data: {
                    ticket: ticket
                },
                beforeSend: function () {
                    $("button#ticket-comments[data-ticket=" + ticket + "]").prop('disabled', true);
                    $("span[data-ticket=" + ticket + "]").show();
                },
                success: function (response) {
                    $("button#ticket-comments[data-ticket=" + ticket + "]").hide();
                    $("span[data-ticket=" + ticket + "]").html(response);
                },
                complete: function () {

                }
            });
        });
    }

    // клик по кнопке ВЫГРУЗИТЬ В ЭКСЕЛЬ
    function downloadInExcel(data) {
        $("button#download-in-excel").click(function () {
            data.excel = true;
            data.email = $("#user-info").data('email');
            console.log(data);
            $.ajax({
                type: 'POST',
                url: '/conversion/viewNotConnected',
                data: {
                    data: data
                },
                beforeSend: function(){
                    // ajaxLoader('show');
                    alert("Подготовка отчета займет некоторое время. Отчет будет отправлен на почтовый ящик " + data.email + ". Для продолжения работы нажмите кнопку.");
                },
                success: function(response){
                    console.log(response);
                },
                complete: function(){
                    // ajaxLoader('hide');
                }
            });
        });
    }

    /**
     * просмотр статистики по каналам продаж
     */
    $("table#table-month a#not-connected-channel-sales").click(function(){
        var data = $(this).data();
        data.year = $("div#filter-nvbs-month div#years-months ul#years li.active").attr('id');
        data.month = $("div#filter-nvbs-month ul#months li.active").attr("id");
        data.period = "month";
        data.email = $("a#user-info").data('email');
        console.log(data);
        $.ajax({
            type: 'POST',
            url: '/conversion/viewNotConnectedChannelSales',
            data: {
                data: data
            },
            beforeSend: function(){
                ajaxLoader('show');
            },
            success: function(response){
                $(".modal-title").html("Информация по каналам продаж");
                $("div#modal div.modal-body").html(response);
                $("div#modal div.modal-footer #add-btn").html('<button type="button" class="btn btn-default" id="report-month-in-excel" data-dismiss="modal">Выгрузить в Excel</button>')
                $("div#modal").show();
                reportInExcel(data);
                viewTicketChannelSales();
            },
            complete: function(){
                ajaxLoader('hide');
            }
        })
    });

    /**
     * Просмотр не выполненых по каналу продаж
     */
    function viewTicketChannelSales() {
        $("a#viewTicketChannelSales").click(function () {
            var data = $(this).data();
            console.log(data);
            $.ajax({
                type: 'POST',
                url: '/conversion/viewTicketNotConnectedChannelSales',
                data: {
                    data: data
                },
                beforeSend: function(){
                    ajaxLoader('show');
                },
                success: function(response){
                    $(".modal-title").html("Не выполненые тикеты по каналу продаж");
                    $("div#modal-not-connected-channel-sales div.modal-body").html(response);
                    $("div#modal-not-connected-channel-sales").show();
                },
                complete: function(){
                    ajaxLoader('hide');
                }
            })
        });
    }

    /**
     * Сохраняем отчет по каналам продаж в эксель и отправляем юзеру.
     */
    function reportInExcel(data)
    {
        $("button#report-month-in-excel").click(function () {
            data.excel = true;
            console.log(data);
            $.ajax({
                type: 'POST',
                url: '/conversion/viewNotConnectedChannelSales',
                data: {
                    data: data
                },
                beforeSend: function () {
                    // ajaxLoader('show');
                    alert("Подготовка отчета займет некоторое время. Отчет будет отправлен на почтовый ящик " + data.email + ". Для продолжения работы нажмите кнопку.");
                },
                success: function (response) {
                    $(".modal-title").html("Информация по каналам продаж");
                    $("div#modal div.modal-body").html(response);
                    $("div#modal div.modal-footer #add-btn").html('<button type="button" class="btn btn-default" id="report-month-in-excel" data-dismiss="modal">Выгрузить в Excel</button>')
                    $("div#modal").show();
                    reportInExcel();
                    viewTicketChannelSales();
                },
                complete: function () {
                    // ajaxLoader('hide');
                }
            });
        });
    }
});