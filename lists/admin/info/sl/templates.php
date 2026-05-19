<p>Tukaj lahko definirate predloge sporočil, ki jih lahko uporabljate za pošiljanje sporočil. Predloga je HTML stran, ki vsebuje vsaj sidro <b>[CONTENT]</b>. V to sidro bo vstavljen tekst sporočila.</P>
<p>Poleg sidra [CONTENT], lahko opcijsko dodate še [FOOTER] in [SIGNATURE] in tako dodate sporočilu nogo in podpis.</p>
<p>Slike v vaši predlogi bodo ravno tako vključena v vaša sporočila. Če dodate sliko v tekst sporočila (content), morajo le te vsebovati popoln URL.</p>
<p><b>Sledenje uporabnikov</b></p>
<p>Za uporabo možnosti sledenja uporabnikom, morate predlogi dodati sidro [USERID], ki bo nadomeščen z ID-jem uporabnika. Ta možnost deluje le, če pošiljate e-pošto v obliki HTML. Nastaviti morate nek URL, ki bo sprejemal ID. Kot možnost lahko uporabite vgrajeno sledenje <?php echo NAME?>. Za to dodajte sidro [USERTRACK] vaši predlogi in sporočilu bo dodana nevidna povezava, ki služi za štetje pogledov sporočila.</p>
<p><b>Slike</b></p>
<p>Pazite kako pišete poti do slik. Vedno uporabite popoln URL. Če slike prenašate s predlogo, naj bodo reference za slike kažejo v eno mapo in ne več različnih map s slikami. Slike naj bodo čim manjše in slik naj bo čim manj. Ne želimo pošiljati velikih sporočil.</p>
