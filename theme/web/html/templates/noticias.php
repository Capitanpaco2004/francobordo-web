<div id="noticias">
	<?php if( $sId ): ?>
		<h3 class="ntca-tile"><?php echo $aNoticia['titulo']; ?></h3>
		<span class="ntca-fcha"> <?php echo $aNoticia['fecha']; ?></span>
		<div class="line"><div></div></div>
		
		<div class="ntca-dscp">
			<div class="text">
				<?php echo $aNoticia['texto']; ?>
			</div>
		</div>
	<?php else: ?>
		<?php while( $aNoticia = tep_db_fetch_array( $aNoticias ) ): ?>
			<?php 
				$sUrl = getSlug( truncate( $aNoticia['titulo'], array( 'SIZE' => 50 ) ) ) . '-n-' . $aNoticia['id_noticia'] . '.html';
			?>
			<div class="cntd">
				<a class="ntca-tile" href="<?php echo $sUrl; ?>"><?php echo $aNoticia['titulo']; ?></a>
				<span class="ntca-fcha"> <?php echo $aNoticia['fecha']; ?></span>
				<div class="line"><div></div></div>
				<a class="icon" title="<?php echo $aNoticia['titulo']; ?>" href="<?php echo $sUrl; ?>" rel="nofollow">Leer el artículo completo</a>
				<div class="text"><?php echo truncate( $aNoticia['texto'], array( 'SIZE' => 500 ) ); ?></div>
				<div class="both"></div>
				<div class="ntca-fter">
					<a href="<?php echo $sUrl; ?>" rel="nofollow">[Leer el artículo completo]</a>
				</div>
			</div>
		<?php endwhile; ?>
		<div class="pgnc"><?php echo $sPaginacion; ?></div>
	<?php endif; ?>
</div>