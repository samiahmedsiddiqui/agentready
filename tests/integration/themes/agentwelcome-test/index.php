<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<title><?php wp_title(); ?></title>
<?php wp_head(); ?>
</head>
<body>
<header class="site-header"><nav><a href="/">Home</a> | <a href="/about/">About</a> | <a href="/contact/">Contact</a></nav></header>
<main class="site-content">
<?php while ( have_posts() ) : the_post(); ?>
<article>
<header class="entry-header"><h1><?php the_title(); ?></h1></header>
<?php the_content(); ?>
<?php $sub = get_post_meta( get_the_ID(), 'subtitle', true ); ?>
<?php if ( $sub ) : ?><p class="acf-subtitle">Subtitle field: <?php echo esc_html( $sub ); ?></p><?php endif; ?>
<?php $cards = get_post_meta( get_the_ID(), 'cards', true ); ?>
<?php if ( $cards ) : ?>
<section class="cards">
<?php foreach ( explode( '|', $cards ) as $card ) : ?>
<div class="card"><h3><?php echo esc_html( $card ); ?></h3><p>Card text for <?php echo esc_html( $card ); ?></p></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<footer class="entry-footer">By Test Author</footer>
</article>
<?php endwhile; ?>
</main>
<footer class="site-footer">Footer text that must not appear</footer>
<?php wp_footer(); ?>
</body>
</html>
