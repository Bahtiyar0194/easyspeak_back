<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация через Telegram</title>
    <style>
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            font-family: system-ui, -apple-system, sans-serif; 
            background: #f4f6f8;
            padding: 30px;
        }
        .card { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            text-align: center;
            min-width: 160px; 
            max-width: 360px;
        }
        button { 
            background: #24A1DE; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 24px; 
            font-weight: 600; 
            cursor: pointer; 
            font-size: 16px; 
            width: 100%;
            transition: background 0.2s;
        }
        button:hover { 
            background: #1d82b4; 
        }
    </style>
    <script src="https://telegram.org/js/telegram-widget.js?22" async></script>
</head>
<body>
    <div class="card">
       <img width="160px" src="data:image/svg+xml,%3csvg%20id='Слой_1'%20data-name='Слой%201'%20xmlns='http://www.w3.org/2000/svg'%20xmlns:xlink='http://www.w3.org/1999/xlink'%20viewBox='0%200%20400%20166'%3e%3cdefs%3e%3cstyle%3e.cls-1{fill:url(%23Безымянный_градиент_11);}.cls-2{fill:%23fff;}%3c/style%3e%3clinearGradient%20id='Безымянный_градиент_11'%20x1='3.8'%20y1='133.93'%20x2='127.58'%20y2='10.15'%20gradientUnits='userSpaceOnUse'%3e%3cstop%20offset='0'%20stop-color='%230d65fd'/%3e%3cstop%20offset='1'%20stop-color='%233ba6f5'/%3e%3c/linearGradient%3e%3c/defs%3e%3ctitle%3eБезымянный-1%3c/title%3e%3cpath%20d='M155.06,70.3V5.9h43.6v11H167.54V32h28.3V42.9h-28.3V59.32h31.72v11Z'/%3e%3cpath%20d='M203.87,57.24c0-8,4.79-12.7,12.82-13.88l16.25-2.45V38.19c0-3.9-2.4-6.26-7.61-6.26-4.87,0-8,2-8.38,6H205.67c.68-10,8.46-15.61,19.91-15.61,11.89,0,18.9,6.26,18.9,16.06V70.3h-11L233,63.13a15.58,15.58,0,0,1-14.27,8.35C209.86,71.48,203.87,65.49,203.87,57.24Zm29.07-4.09V49.43l-12,1.73c-3.68.54-5.56,2.18-5.56,5.44S218,62,222.34,62C228.15,62,232.94,58.32,232.94,53.15Z'/%3e%3cpath%20d='M250.72,55.42h11.45c.68,4.63,4,7,8.89,7,4.53,0,7.61-1.82,7.61-5.17,0-2.91-2.05-4.63-10-6.08-11.29-2-16.5-5.81-16.5-14.24,0-8.8,7.61-14.61,18.38-14.61,11.37,0,18.29,5.54,19,15.15h-11.2c-.52-4.08-3.51-6.26-7.95-6.26-4.11,0-6.76,1.82-6.76,4.9,0,2.9,2.23,4.45,9.75,5.72,12,2,17,6.07,17,14.24,0,9.25-7.52,15.42-19.4,15.42C258.67,71.48,251.48,65.58,250.72,55.42Z'/%3e%3cpath%20d='M302.26,85.72,308,69.84,291.23,23.49h12.66l10.43,32.66L324.4,23.49h12.23L314.57,85.72Z'/%3e%3cpath%20d='M152.75,123.82h12.57c.85,7.71,6,11.34,13.68,11.34,7.09,0,11.37-2.72,11.37-8v-.37c0-4.8-3.25-7.07-15.05-9.43-14.79-2.9-20.52-8.89-20.52-19v-.54c0-11.34,9.24-18.78,22.91-18.78s23.08,7.17,24.11,20.5H189.25c-.68-6.62-4.95-9.88-11.79-9.88s-10.18,3.26-10.18,7.8v.18c0,4.08,1.8,6.62,13.77,8.8,14.53,2.63,21.8,8.16,21.8,19V126c0,12-9.84,19.86-24.2,19.86C163.18,145.86,153.52,137.7,152.75,123.82Z'/%3e%3cpath%20d='M209.51,160.1V97.88h11.2l.34,7.17a15.59,15.59,0,0,1,14.19-8.35c11.8,0,18.89,10.16,18.89,24v1.18c0,13.87-7.26,24-19.14,24a15.11,15.11,0,0,1-13.42-7.8v22ZM242,121.83v-1.18c0-9.16-3.59-14.24-10.43-14.24s-10.43,5.08-10.43,14.24v1.18c0,9.16,3.67,14.24,10.43,14.24S242,131,242,121.83Z'/%3e%3cpath%20d='M259.69,121.83v-1.18c0-14.24,8.81-24,22.74-24,15.05,0,22.48,11.25,21.71,27.57H271.92c.59,8,4.1,11.89,10.51,11.89,5.39,0,8.46-2.72,9.57-6.35h11.89c-2.14,9.88-9.92,16.05-21.46,16.05C268.24,145.86,259.69,136.25,259.69,121.83Zm32.23-5.9c-.51-5.9-3.59-9.52-9.58-9.52s-9.23,3.08-10.25,9.52Z'/%3e%3cpath%20d='M309.1,131.62c0-8,4.79-12.7,12.82-13.88l16.25-2.45v-2.72c0-3.9-2.4-6.25-7.61-6.25-4.87,0-7.95,2-8.38,6H310.9c.68-10,8.46-15.6,19.91-15.6,11.89,0,18.9,6.26,18.9,16.06v31.92h-11l-.43-7.16A15.57,15.57,0,0,1,324,145.86C315.08,145.86,309.1,139.88,309.1,131.62Zm29.07-4.08v-3.72l-12,1.72c-3.68.55-5.56,2.18-5.56,5.45s2.57,5.35,6.93,5.35C333.38,136.34,338.17,132.71,338.17,127.54Z'/%3e%3cpath%20d='M369.88,128v16.69h-12V77.56h12v37.1l15.64-16.78h14l-17,18,17.7,28.84H386.89l-12.82-21.13Z'/%3e%3cpath%20class='cls-1'%20d='M137.73,34.67v68.62A34.66,34.66,0,0,1,103.07,138H69.27L41.81,164.91c-2.45,1.47-5.26,1.44-7,0a5.06,5.06,0,0,1-1.61-2.7V137.94A34.67,34.67,0,0,1-.23,103.29V34.67A34.67,34.67,0,0,1,34.44,0h68.63A34.66,34.66,0,0,1,137.73,34.67Z'/%3e%3crect%20class='cls-2'%20x='27.8'%20y='26.68'%20width='81.91'%20height='14.85'%20rx='7.42'/%3e%3crect%20class='cls-2'%20x='27.8'%20y='56.89'%20width='64'%20height='14.85'%20rx='7.42'/%3e%3crect%20class='cls-2'%20x='27.8'%20y='90.17'%20width='44.03'%20height='14.85'%20rx='7.42'/%3e%3c/svg%3e">
        <p>{{ __('auth.telegram.description') }}</p>
        <button id="telegram-btn" onclick="openTelegram()">{{ __('auth.telegram.button') }}</button>
    </div>

    <script>
        function openTelegram() {
            if (!window.Telegram || !window.Telegram.Login) {
                alert("{{ __('auth.telegram.script_error') }}");
                return;
            }

            window.Telegram.Login.auth({
                bot_id: '{{ $botId }}',
                request_access: 'write'
            }, function(data) {
                if (!data) {
                    window.location.href = '{{ $returnUrl }}/auth/login?error=cancelled';
                    return;
                }
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/api/v1/auth/telegram/callback';

                const fields = {
                    ...data,
                    school_id: '{{ $schoolId }}',
                    return_url: '{{ $returnUrl }}',
                    lang_tag: '{{ $langTag }}'
                };

                for (const key in fields) {
                    if (fields[key] !== undefined && fields[key] !== null) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = fields[key];
                        form.appendChild(input);
                    }
                }

                document.body.appendChild(form);
                form.submit();
            });
        }
    </script>
</body>
</html>