wordpress

#RU

== Установка ==

1. Убедитесь что у вас установлена последняя версия плагина Restrict Content Pro
2. Распакуйте этот плагин в директорию `/wp-content/plugins/`(либо установите вложенный архив через панель администратора)
3. Активируйте плагин в меню "Плагины"

Так же необходимо добавить в файл ```wp-content\plugins\restrict-content-pro-master\includes\admin\settings\settings.php```
следующие строки перед строкой ```<?php if( ! function_exists( 'rcp_register_stripe_gateway' ) ) : ?>```
```
<?php if( function_exists( 'pw_rcp_register_fondy_gateway' ) ) : ?>
                            <tr valign="top">
                                <th colspan=2>
                                    <h3><?php _e('Fondy Settings', 'rcp'); ?></h3>
                                </th>
                            </tr>
                            <tr>
                                <th>
                                    <label for="rcp_settings[fondy_secret]"><?php _e( 'Fondy Secret Key', 'rcp' ); ?></label>
                                </th>
                                <td>
                                    <input class="regular-text" id="rcp_settings[stripe_test_secret]" style="width: 300px;" name="rcp_settings[fondy_secret]" value="<?php if(isset($rcp_options['fondy_secret'])) { echo $rcp_options['fondy_secret']; } ?>"/>
                                    <p class="description"><?php _e('Enter your test secret key.', 'rcp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label for="rcp_settings[fondy_merchant_id]"><?php _e( 'Fondy MID', 'rcp' ); ?></label>
                                </th>
                                <td>
                                    <input class="regular-text" id="rcp_settings[fondy_merchant_id]" style="width: 300px;" name="rcp_settings[fondy_merchant_id]" value="<?php if(isset($rcp_options['fondy_merchant_id'])) { echo $rcp_options['fondy_merchant_id']; } ?>"/>
                                    <p class="description"><?php _e('Enter your Merchant ID', 'rcp'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label for="rcp_settings[fondy_reccuring]"><?php _e( 'Fondy Subscription', 'rcp' ); ?></label>
                                </th>
                                <td>
                                    <input class="checkbox" type="checkbox" id="rcp_settings[fondy_reccuring]" style="width: 15px;" name="rcp_settings[fondy_reccuring]" value="1" <?php if( isset( $rcp_options['fondy_reccuring'] ) ) checked('1', $rcp_options['fondy_reccuring']); ?>/>
                                    <p class="description"><?php _e('Enable Subscription', 'rcp'); ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
```
== Конфигурация ==

1. Зайдите в "Ограничения > Настройки > Платежи" и найдите Fondy
2. Введите параметры Fondy Secret Key, Fondy MID.
3. Сохраните настройки.
