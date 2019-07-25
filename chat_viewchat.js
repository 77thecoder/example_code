// Initialize variables

var userName;
var userID;
var userOP;
var chatId = localStorage.getItem('chat_room_id');

var $window = $(window);
var $usernameInput = $('.usernameInput');
var $messages = $('#messages-list');
var $inputMessage = $('.inputMessage');
var $currentInput = $inputMessage.focus();

(function() {
    $(function() {
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
                userID = userInfo.uid;
                userOP = userInfo.city;

                localStorage.setItem('user_id', userID);

                init();
            }
        });

        $('#issue-solve button').on('click', function() {
          var isSolved = $(this).data('answer');
          // var chatId = localStorage.getItem('chat_room_id');
          var text;
          var status;

          if (isSolved) {
            text = 'Ваш вопрос решен.';
            status = 'Решен';
          } else {
            text = 'Ваш вопрос не решен. Пожалуйста, обратитесь в КЦ.';
            status = 'Не решен';
          }

          $inputMessage.val(text);

          var message = $inputMessage.val();

          handleMessage(userName, userID, message, chatId);

          $.ajax('/chat/api/updateChatStatus.php', {
              cache: false,
              async: false,
              data: {
                  chat_room_id: chatId,
                  chat_status: status
              },
              error: function(jqXHR, textStatus, errorThrown) {
                  console.dir(textStatus, jqXHR, errorThrown);
              },
              method: 'GET',
              success: function(data) {
                  console.dir(data);
              }
          });

          $('#message-field').attr('readonly', 'readonly');
          $('#send-message').attr('disabled', 'disabled');

          window.location.href = '/chat/public/admin/admin.php';

        });

    });

    function init() {

        chatWorker(chatId);

        $.ajax('/chat/api/getChatData.php', {
            cache: false,
            data: {
                chat_room_id: chatId
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.dir(textStatus, jqXHR, errorThrown);
            },
            method: 'GET',
            success: function(data) {
                console.dir(data);

                var chatInfo = JSON.parse(data);

                var html = '<p>Номер тикета: '+chatInfo.ticketcode+'<br />';
                html += 'Номер контракта: '+chatInfo.contract_number+'<br />';
                html += 'Логин абонента: '+chatInfo.client_login+'</p>';
                $('#chat-info').html(html);
            }
        });

        updateMessageStatus(chatId, userID);

        // Переход по ссылке конкретного чата

        $(document).on('click', '.appoint-chat', function(e) {
            e.preventDefault();

            var chatId = $(this).data('chat-id');

            $.ajax('/chat/api/updateChatStatus.php', {
                cache: false,
                async: false,
                data: {
                    chat_room_id: chatId,
                    chat_status: 'Взято в работу'
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.dir(textStatus, jqXHR, errorThrown);
                },
                method: 'GET',
                success: function(data) {
                    console.dir(data);
                    $(this).hide();
                }
            });

            
        });

        checkChatStatus(chatId);

        // Keyboard events

        $window.keydown(event => {
            // Auto-focus the current input when a key is typed
            if (!(event.ctrlKey || event.metaKey || event.altKey)) {
                $currentInput.focus();
            }

            // When the client hits ENTER on their keyboard
            if (event.which === 13 && !event.shiftKey && !mobilecheck()) {
              event.preventDefault();
              
              var message = $inputMessage.val();

              handleMessage(userName, userID, message, chatId);
            }
        });

        $(document).on('click', '#send-message', function(e) {
          e.preventDefault();
          
          var message = $inputMessage.val();

          handleMessage(userName, userID, message, chatId);
        });

        if (document.hidden) {
            var audio = new Audio('/chat/audio/unconvinced.mp3');
            var promise = audio.play();
        }

    } // init

})();
