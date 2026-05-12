<html>
<body>
<form action="{action}" id="errorform">
    <input type="hidden" value="{message}"/>
</form>
<script>
    document.getElementById('errorform').submit();
</script>
</body>
</html>