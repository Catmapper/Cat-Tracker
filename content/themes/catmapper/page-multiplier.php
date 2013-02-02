<?php

set_time_limit( 0 );
$has_calculated = false;

if ( isset( $_POST['calculate_animals'] ) ) {

	$number_of_years = absint( $_POST['number_of_years'] );
	$can_have_babies_at = absint( $_POST['can_have_babies_at'] );
	$months_between_litters = absint( $_POST['months_between_litters'] );
	$number_of_males_per_litter = absint( $_POST['number_of_males_per_litter'] );
	$number_of_females_per_litter = absint( $_POST['number_of_females_per_litter'] );
	$death_percentage_per_litter = absint( $_POST['death_percentage_per_litter'] );

	$adult_males = 0;
	$adult_females = 0;

	// we start with 2 child animals
	$child_males = 1;
	$child_females = 1;

	$number_of_months = ( $number_of_years * 12 );
	$months = range( 0, $number_of_months );

	// consider death or not
	$consider_death = false;
	if ( $death_percentage_per_litter > 0 && $death_percentage_per_litter < 100 ) {
		$death_percentage = $death_percentage_per_litter / 100;
		$consider_death = true;
	}


	foreach ( $months as $month ) {

		// animals becoming adults
		if ( 0 != $month && 1 != $month && 0 == ( $month % $can_have_babies_at ) ) {

			for ( $i = 0; $i < $child_females; $i++ ) {
				$adult_females++;
			}

			for ( $i = 0; $i < $child_males; $i++ ) {
				$adult_males++;
			}

			$child_females = $child_males = 0;
		}

		// animals reproducing
		if ( 0 != $month && 1 != $month && 0 == ( $month % $months_between_litters ) ) {
			for ( $i = 0; $i < $adult_females; $i++ ) {

				$child_females += ( $consider_death ) ? $number_of_females_per_litter - ( $death_percentage * $number_of_females_per_litter ) : $number_of_females_per_litter;
				$child_males += ( $consider_death ) ? $number_of_males_per_litter - ( $death_percentage * $number_of_females_per_litter ) : $number_of_males_per_litter;
			}
		}

	}


	$total = $adult_females + $child_females + $adult_males + $child_males;
	$has_calculated = true;
}

get_header(); ?>

	<div id="primary" class="site-content">
		<div id="content" role="main">

			<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'format-aside' ); ?>>
					<header class="entry-header">
						<h1 class="entry-title"><?php the_title(); ?></h1>
					</header>

					<div class="entry-content">
						<?php
						if ( $has_calculated ) {
							echo '<div class="aside">';
							printf( 'In %d years, there will be %s adult males, %s adult females, %s male children and %s female children - for a total of %s animals.', $number_of_years, number_format( $adult_males ), number_format( $adult_females ), number_format( $child_males ), number_format( $child_females ), number_format( $total ) );
							echo '</div>';
						}
						echo '<p>Fill in the variables below and this page will automagically calculate the number of animals that will offspring from a single male and female based on your provided numbers.</p>';
						echo '<form method="post">';
						echo '<fieldset>';
						echo '<p><label for="number_of_years">Number of years:</label> <input name="number_of_years" id="number_of_years" type="number" min="1" max="25" value="';
								echo ( isset( $_POST['number_of_years'] ) ) ? absint( $_POST['number_of_years'] ) : 7;
							echo '"></p>';
						echo '<p><label for="can_have_babies_at">Number of months after which a child animal can have babies:</label> <input name="can_have_babies_at" id="can_have_babies_at" type="number" min="0" max="36" value="';
								echo ( isset( $_POST['can_have_babies_at'] ) ) ? absint( $_POST['can_have_babies_at'] ) : 6;
							echo '"></p>';
						echo '<p><label for="months_between_litters">Number of months between litters:</label> <input name="months_between_litters" id="months_between_litters" type="number" min="1" max="36" value="';
								echo ( isset( $_POST['months_between_litters'] ) ) ? absint( $_POST['months_between_litters'] ) : 6;
							echo '"></p>';
						echo '<p><label for="number_of_males_per_litter">Number of males per litter:</label> <input name="number_of_males_per_litter" id="number_of_males_per_litter" type="number" min="0" max="25" value="';
								echo ( isset( $_POST['number_of_males_per_litter'] ) ) ? absint( $_POST['number_of_males_per_litter'] ) : 2;
							echo '"></p>';
						echo '<p><label for="number_of_females_per_litter">Number of females per litter:</label> <input name="number_of_females_per_litter" id="number_of_females_per_litter" type="number" min="0" max="25" value="';
								echo ( isset( $_POST['number_of_females_per_litter'] ) ) ? absint( $_POST['number_of_females_per_litter'] ) : 2;
							echo '"></p>';
						echo '<p><label for="death_percentage_per_litter">Mortality rate (percentage that die before having first litter):</label> <input name="death_percentage_per_litter" id="death_percentage_per_litter" type="number" min="0" max="99" value="';
								echo ( isset( $_POST['death_percentage_per_litter'] ) ) ? absint( $_POST['death_percentage_per_litter'] ) : 20;
							echo '"></p>';
						echo '<p><input type="submit" value="Calculate" name="calculate_animals"></p>';
						echo '</form>';
						echo '</fieldset>';
						?>
					</div>
				</article>
			<?php endwhile; ?>

		</div>
	</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
