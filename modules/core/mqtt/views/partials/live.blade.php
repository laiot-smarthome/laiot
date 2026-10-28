@push('scripts')
    <script>
        (function () {
            if (window.__mdMqttLive) {
                return;
            }
            window.__mdMqttLive = true;

            // Обновление состояния MQTT-цикла и списка топиков раз в 5 секунд.
            // Компоненты слушают mqtt-refresh и перерисовываются сервером через
            // /yoyo; пока пользователь работает с формой списка или открыт
            // диалог подтверждения, обновление пропускается.
            window.setInterval(function () {
                if (document.hidden || !window.Yoyo) {
                    return;
                }
                if (!document.querySelector('[data-md-mqtt-live]')) {
                    return;
                }
                if (document.querySelector('.md-modal.show')) {
                    return;
                }
                var topics = document.getElementById('mqtt-topics');
                if (topics && topics.contains(document.activeElement)) {
                    return;
                }
                window.Yoyo.dispatch('mqtt-refresh');
            }, 5000);
        })();
    </script>
@endpush
