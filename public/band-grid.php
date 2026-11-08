<?php

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode("songcontest_band_grid", "scp_band_grid");

function scp_band_grid($atts = []) {

    global $wpdb;

    $atts = shortcode_atts(
        [
            'contest_id' => ''
        ],
        $atts,
        'songcontest_band_grid'
    );

    $artists_table  = $wpdb->prefix . 'scp_artists';
    $contests_table = $wpdb->prefix . 'scp_contests';

    /*
     * Ohne contest_id:
     * Alle archivierten Contests anzeigen.
     *
     * Mit contest_id:
     * Nur Teilnehmer dieses Contests anzeigen,
     * unabhängig vom Contest-Status.
     */

    $contest_id = trim((string) $atts['contest_id']);

    if ($contest_id !== '') {

        if (
            !ctype_digit($contest_id)
            || intval($contest_id) <= 0
        ) {
            return "<div class='scp-band-grid-empty'>
                Ungültige Contest-ID.
            </div>";
        }

        $where_contest = $wpdb->prepare(
            "c.id = %d",
            intval($contest_id)
        );

    } else {

        $where_contest =
            "c.status IN ('archived', 'archieved')";
    }

    $sql = "
        SELECT
            a.id,
            a.contest_id,
            a.name,
            a.title,
            a.image,
            a.audio,
            a.status,

            c.name AS contest_name,
            c.year AS contest_year,
            c.status AS contest_status

        FROM $artists_table a

        INNER JOIN $contests_table c
            ON c.id = a.contest_id

        WHERE $where_contest

        AND a.status IN (
            'approved',
            'top20',
            'final',
            'finale',
            'winner'
        )

        ORDER BY
            c.year DESC,
            c.id DESC,
            a.name ASC
    ";

    $rows = $wpdb->get_results($sql);

    if (empty($rows)) {

        return "
            <div class='scp-band-grid-empty'>
                Keine Teilnehmer für diese Auswahl vorhanden.
            </div>
        ";
    }

    $html = "<div class='scp-band-grid scp-alumni-grid'>";

    foreach ($rows as $row) {

        $image = !empty($row->image)
            ? esc_url(scp_image_url($row->image, '500'))
            : '';

        $contest_label = "Contest #" . intval($row->contest_id);

        if (!empty($row->contest_year)) {

            $contest_label .= " · " . intval($row->contest_year);
        }

        $is_winner = ($row->status === 'winner');

        $is_top5 = in_array(
            $row->status,
            ['final', 'finale', 'winner'],
            true
        );

        $card_classes = ['scp-grid-card'];

        if ($is_winner) {
            $card_classes[] = 'scp-grid-winner';
        } elseif ($is_top5) {
            $card_classes[] = 'scp-grid-top5';
        }

        $html .= "
        <article
            class='" . esc_attr(implode(' ', $card_classes)) . "'
            style='background-image:url(\"" . $image . "\");'
        >";

        /*
         * Gewinner-Markierung
         */

        if ($is_winner) {

            $html .= "
                <div
                    class='scp-grid-placement scp-grid-placement-winner'
                    title='Gewinner des Contests'
                    aria-label='Gewinner des Contests'
                >
                    1
                </div>
            ";

        /*
         * Top-5-Markierung
         */

        } elseif ($is_top5) {

            $html .= "
                <div
                    class='scp-grid-placement scp-grid-placement-top5'
                    title='Top 5 des Contests'
                    aria-label='Top 5 des Contests'
                >
                    TOP 5
                </div>
            ";
        }

        $html .= "
            <div class='scp-grid-overlay'>

                <div class='scp-grid-contest'>
                    " . esc_html($contest_label) . "
                </div>

                <h3>
                    " . esc_html($row->name) . "
                </h3>
        ";

        if (!empty($row->title)) {

            $html .= "
                <p class='scp-grid-song-title'>
                    " . esc_html($row->title) . "
                </p>
            ";
        }

        if (!empty($row->audio)) {

            $html .= "
                <audio
                    controls
                    controlsList='nodownload'
                    preload='none'
                >
                    <source
                        src='" . esc_url($row->audio) . "'
                        type='audio/mpeg'
                    >
                </audio>
            ";
        }

        $html .= "
            </div>
        </article>
        ";
    }

    $html .= "</div>";

    return $html;
}
