<?php

use Alexa\Request\IntentRequest;

class Alexa_News {
	public $numbers = array(
		'erster' => 1,
		'1' => 1,
		'zweiter' => 2,
		'2' => 2,
		'dritter' => 3,
		'3' => 3,
		'vierter' => 4,
		'4' => 4,
		'fünfter' => 5,
		'5' => 5,
	);

	public $placement = array(
		'erster Beitrag',
		'zweiter Beitrag',
		'dritter Beitrag',
		'vierter Beitrag',
		'fünfter Beitrag',
	);

	public function news_request( $event ) {

		$request = $event->get_request();
		$response = $event->get_response();

		if ( $request instanceof Alexa\Request\IntentRequest ) {
			$intent = $request->intentName;
			switch ( $intent ) {
				case 'Latest':
					$term_slot = strtolower( sanitize_text_field( $request->getSlot( 'TermName' ) ) );

					$args = [
						'post_type'      => alexawp_news_post_types(),
						'posts_per_page' => max( 1, min( 100, count( $this->placement ) ) ),
						'tax_query'      => [],
					];

					if ( $term_slot ) {
						$news_taxonomies = alexawp_news_taxonomies();

						if ( $news_taxonomies ) {
							/*
							 * TODO:
							 *
							 * Support for 'name__like'?
							 * Support for an 'alias' meta field?
							 * Support for excluding terms?
							 */
							$terms = get_terms( [
								'name' => $term_slot,
								'taxonomy' => $news_taxonomies,
							] );

							if ( $terms ) {
								// 'term_taxonomy_id' query allows omitting 'taxonomy'.
								$args['tax_query'][] = [
									'terms' => wp_list_pluck( $terms, 'term_taxonomy_id' ),
									'field' => 'term_taxonomy_id',
								];
							}
						}
					}

					if ( $term_slot && ! $args['tax_query'] ) {
						$response->respond( __( "Sorry! ich konnte keine Nachrichten zu diesem Thema finden.", 'alexawp' ) )->endSession();
						break;
					}

					$result = $this->endpoint_content( $args );

					$response
						->respond( $result['content'] )
						/* translators: %s: site title */
						->withCard( sprintf( __( 'Die aktuellen Beiträge %s', 'alexawp' ), get_bloginfo( 'name' ) ) )
						->addSessionAttribute( 'post_ids', $result['ids'] );
					break;
				case 'ReadPost':
					if ( $post_number = $request->getSlot( 'PostNumberWord' ) ) {
						$post_number = $this->numbers[ $post_number ] -1;
					} elseif ( $post_number = $request->getSlot( 'PostNumber' ) ) {
						$post_number = $post_number - 1;
					}
					$post_ids = $request->session->attributes['post_ids'];
					$result = $this->endpoint_single_post( $post_ids[ $post_number ] );
					$response->respond( $result['content'] )->withCard( $result['title'] )->endSession();
					break;
				case 'AMAZON.StopIntent':
					$response->respond( __( 'Bis demnächst', 'alexawp' ) )->endSession();
					break;
				default:
					# code...
					break;
			}
		} elseif ( $request instanceof Alexa\Request\LaunchRequest ) {
			$response->respond( __( "Frag mich nach neuen Beiträgen!", 'alexawp' ) );
		}
	}

	private function endpoint_single_post( $id ) {
		$single_post = get_post( $id );
		$post_content = strip_tags( strip_shortcodes( $single_post->post_content ) );
		return array(
			'content' => $post_content,
			'title' => $single_post->post_title,
		);
	}

	private function endpoint_content( $args ) {
		$news_posts = get_posts( array_merge( $args, [
			'no_found_rows' => true,
			'post_status' => 'publish',
		] ) );

		$content = '';
		$ids = array();
		if ( ! empty( $news_posts ) && ! is_wp_error( $news_posts ) ) {
			foreach ( $news_posts as $key => $news_post ) {
				// TODO: Sounds a little strange when there's only one result.
				$content .= $this->placement[ $key ] . ', ' . $news_post->post_title . '. ';
				$ids[] = $news_post->ID;
			}
		}

		return array(
			'content' => $content,
			'ids' => $ids,
		);
	}
}
