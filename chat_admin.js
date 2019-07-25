(function() {

    var userName;
    var chatId;

    var $messages = $('.messages');
    var $window = $(window);
    var $inputMessage = $('.inputMessage');

    $.ajax('/chat/api/getUserInfo.php', {
        cache: false,
        error: function(jqXHR, textStatus, errorThrown) {
            console.dir(textStatus, jqXHR, errorThrown);
        },
        method: 'GET',
        success: function(data) {
            var userInfo = JSON.parse(data);

            // console.dir(userInfo);

            userName = userInfo.fio;

            init();
        }
    });

    function init() {
        $(function () {

            // Отображаем кол-во открытых чатов

            renderNumChats('Открыто');

            // Обработка взятия в работу

            $(document).on('click', '#accept-chat', function(e) {
                e.preventDefault();

                $.ajax('/chat/api/appointChat.php', {
                    cache: false,
                    async: false,
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log(textStatus);
                    },
                    method: 'GET',
                    success: function(data) {
                        // console.dir(data);

                        var response = JSON.parse(data);

                        if (response.status === 'error') {
                            alert(response.response);
                            return false;
                        }

                        var assignedChatList = document.getElementById('assigned-chats-list');

                        assignedChatList.innerHTML = '';

                        displayChatList('Взято в работу', 'assigned-chats-list', false);
                    },
                    complete: function() {
                        
                    }
                });
            });

            $(document).on('click', '.open-chat', function(e) {
                e.preventDefault();

                var url = $(this).attr('href');

                var chatId = $(this).data('chat-id');

                localStorage.setItem('chat_room_id', chatId);

                /*$('#chat-frame').attr('src', 'viewchat.php?chat_id=' + chatId);
                $('.chat-popup-container').show();
                $('.chat-overlay').show();*/

                window.location.href = url;
            });

            $(document).on('click', '#close-chat-frame', function(e) {
                e.preventDefault();
                $('.chat-popup-container').hide();
                $('#chat-frame').attr('src', '');
                $('.chat-overlay').hide();
            });

            // Очищаем окно чата перед загрузкой и отрисовкой сообщений 

            var chatList = document.getElementById('assigned-chats-list');

            chatList.innerHTML = '';

            displayChatList('Взято в работу', 'assigned-chats-list');

            // var assignedChatList = document.getElementById('assigned-chats-list');

            // assignedChatList.innerHTML = '';

            // displayChatList('Взято в работу', 'assigned-chats-list');

            // Переход по ссылке конкретного чата

            // $(document).on('click', '.view-chat', function(e) {
            //     e.preventDefault();

            //     var chatId = $(this).data('chat-id');

            //     localStorage.setItem('chat_room_id', chatId);

            //     window.location.href = e.target.href;
            // });

            // Переключатель звука уведомлений о новых сообщениях

            $('#toggle-sound-btn').on('click', function(e) {
                var isSoundOff = localStorage.getItem('sound');
                
                e.preventDefault();
                if (isSoundOff === '1') {
                    localStorage.setItem('sound', '0');
                    $('#sound-icon').attr('src', 'images/sound-off.png');
                } else {
                    localStorage.setItem('sound', '1');
                    $('#sound-icon').attr('src', 'images/sound-on.png');
                }

            });

            $('#show-closed-chats').on('click', function(e) {
                e.preventDefault();

                displayChatList('Решен', 'solved-chats', false);
                displayChatList('Не решен', 'unsolved-chats', false);
            });

        });
    }

    

    function renderNumChats(status) {

        $.ajax('/chat/api/getNumChats.php', {
            cache: false,
            data: {
                status: status
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log(textStatus);
            },
            method: 'GET',
            success: function(data) {
                var result = JSON.parse(data);

                $('#num-chats').text(result.num);

                var currentQueueNum = localStorage.getItem('chat_queue_num');
                if (result.num > currentQueueNum) {
                    if (!Notify.needsPermission) {
                        doNotification('Новое обращение', 'Добавилось новое обращение');
                    } else if (Notify.isSupported()) {
                        Notify.requestPermission(onPermissionGranted, onPermissionDenied);
                    }
                }

                if (result.num > 0) {
                    $('#accept-chat').show();
                }

                localStorage.setItem('chat_queue_num', result.num);
            },
            complete: function() {
                setTimeout(renderNumChats.bind(null, status), 5000);
            }
        });
    }

})();
