<a id="box-chat" class="box" href="javascript:void(window.open('<?php echo $sUrl; ?>soporte/chat.php','','width=590,height=610,left=0,top=0,resizable=yes,menubar=no,location=no,status=yes,scrollbars=yes'))">
	<img src="<?php echo $sUrl; ?>soporte/image.php?id=01&amp;type=inlay" width="240" height="104" border="0" alt="LiveZilla Live Help">
</a>
<div id="livezilla_tracking" style="display:none"></div>
<script type="text/javascript">
	var script = document.createElement("script");
	script.type="text/javascript";
	var src = "<?php echo $sUrl; ?>soporte/server.php?request=track&output=jcrpt&nse="+Math.random();setTimeout("script.src=src;document.getElementById('livezilla_tracking').appendChild(script)",1);
</script>
<noscript>
	<img src="<?php echo $sUrl; ?>soporte/server.php?request=track&amp;output=nojcrpt" width="0" height="0" style="visibility:hidden;" alt=""/>
</noscript>