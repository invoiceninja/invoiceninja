<?php
/**
 * @OA\Schema(
 *   schema="Quote",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="______"),
 *       @OA\Property(property="total_taxes", type="number", format="float", example="10.00", description="The total taxes for the quote"),
 *       @OA\Property(property="next_send_date", type="string", format="date", example="1994-07-30", description="The Next date for a reminder to be sent"),
 * )
 */