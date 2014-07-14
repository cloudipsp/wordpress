wordpress
=========

== Установка ==

1. Убедитесь что у вас установлена последняя версия плагина WooCommerce (WooCommerce 2.0+)
2. Распакуйте этот плагин в директорию `/wp-content/plugins/`
3. Активируйте плагин в меню "Плагины"


== Конфигурация ==

1. Зайдите в "WooCommerce > Настройки > Оплата"
2. Зайдите на таб "Oplata". Если его нет - активируйте плагин.
3. Разрешите этот способ оплаты (Enable). Назовите его "Online Payments"
4. Заполните поля `Merchant Key` и `Merchant Salt` данными полученными от Oplata.com
5. Выберите хотите ли вы показывать логотип платежки
6. Выберите `Return Page` куда будет напрявлять пользователей платежка после окончания работы
7. Сохраните настройки



== Configuration ==

1. Visit the `WooCommerce > Settings > Checkout` tab.
2. Click on *Oplata* to edit the settings. If you do not see *Oplata* in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it `Online Payments` (this will show up on the payment page your customer sees).
4. Add in your `Merchant Key` and `Merchant Salt` as provided by the Oplata Team.
5. Choose if you want to show the `Oplata` Logo to the customer (You may also insert a custom logo in your discription via `<img ...` tag).
6. Select `Return Page` (URL you want PayUMoney to redirect after payment).
7. Click Save.

== Installation ==

1. Ensure you have latest version of WooCommerce plugin installed (WooCommerce 2.0+)
2. Unzip and upload contents of the plugin to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress



![Скриншот][1]
----

[1]: https://raw.githubusercontent.com/oplatacom/wordpress/master/settings.png