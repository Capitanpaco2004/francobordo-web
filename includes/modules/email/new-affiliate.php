{% extends base.php %}

{% block content %}
	<?php
	
		/**
	             * #XCC-313-91043
	             * @author Daniel Lucia <daniel.lucia@denox.es>
	             */
		     
		echo '<span style="font-size: 24px;">' . sprintf( trim( UHE_GREET_NONE ), $name ) . '</span><br/><br/>';
		echo str_replace( array("\r\n", "\n\r", "\n", "\r", "\t"), '<br />', UHE_WELCOME . UHE_TEXT . UHE_CONTACT . UHE_WARNING );
	?>
{% endblock %}
