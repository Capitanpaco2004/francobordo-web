
<form class="cookies-config" method="POST" action="<?php echo tep_href_link(basename(__FILE__), 'action=process'); ?>">
    <ul>
        <?php foreach($cookieList as $cookieId => $cookie): ?>
        <li>
            <div class="xform check rgpd-check">
                <input <?php if ($cookie['checked']): ?> checked="checked" <?php endif; ?>type="checkbox" name="<?php echo $cookieId; ?>" value="true" id="<?php echo $cookieId; ?>"><label style="margin-right: 0px;" for="<?php echo $cookieId; ?>"><span></span> <?php echo $cookie['title']; ?></label>
            </div>
            <p>
                <?php echo $cookie['text']; ?>
            </p>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="botonera"><?php echo tep_image_submit('button_continue.gif', COOKIES_SAVE); ?></div>

</form>
