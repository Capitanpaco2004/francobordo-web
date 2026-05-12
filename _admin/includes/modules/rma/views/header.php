<div class="rmaContainer">
    <div class="oeHead column a12 row ax amiddle">
        <div class="oeTitu column a03 T12 logo">
            <i class="fa fa-file-text"></i> <b>&nbsp;RMA
            <small>&nbsp;<?php echo $sTitle; ?></small></b>
        </div>
        <div class="oeButton column a09 T12 dtright">
            <a class="xbutton hv8 small verde" href="<?php echo tep_href_link('rma.php', 'action=list'); ?>" title="Listado de devoluciones"><i class="fa fa-cog"></i>Listado de devoluciones</a>
            <a class="xbutton hv8 small" href="<?php echo tep_href_link('rma.php', 'action=options-return'); ?>" title="Razones devolución"><i class="fa fa-cog"></i>Razones devolución</a>
            <a class="xbutton hv8 small" href="<?php echo tep_href_link('rma.php', 'action=types-return'); ?>" title="Tipos de retorno"><i class="fa fa-cog"></i>Tipos de retorno (Envío)</a>
            <a class="xbutton hv8 small" href="<?php echo tep_href_link('rma.php', 'action=payment-method'); ?>" title="Métodos de pago"><i class="fa fa-cog"></i>Métodos de reembolso (Pago)</a>
            <a class="xbutton hv8 small" href="<?php echo tep_href_link('rma.php', 'action=status'); ?>" title="Estados"><i class="fa fa-cog"></i>Estados</a>
        </div>
    </div>
