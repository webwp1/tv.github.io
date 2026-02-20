<?php get_header(); ?>

<main style="padding: 50px 20px; color: white; text-align: center;">
    <h1><?php bloginfo('name'); ?></h1>
    <p><?php bloginfo('description'); ?></p>
    
    <div class="stream-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 40px;">
        <?php
        if (have_posts()) : 
            while (have_posts()) : the_post(); ?>
                <article style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 15px;">
                    <h2><?php the_title(); ?></h2>
                    <a href="<?php the_permalink(); ?>" style="color: #3498db;">Otevřít</a>
                </article>
            <?php endwhile;
        endif;
        ?>
    </div>
</main>

<?php get_footer(); ?>