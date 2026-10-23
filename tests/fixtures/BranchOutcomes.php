<?php

/**
 * Visit every block while leaving a loop exit untested when the limit is two.
 *
 * @param int $limit
 * @return int
 */
function low_widont_branch_fixture($limit)
{
    $iteration = 0;
    do {
        $iteration++;
        if ($iteration === 2) {
            break;
        }
    } while ($iteration < $limit);
    return $iteration;
}
