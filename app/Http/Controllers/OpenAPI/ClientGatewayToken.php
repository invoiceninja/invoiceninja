<?php
/**
 * @OA\Schema(
 *   schema="ClientGatewayToken",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="The hashed id of the client gateway token"),
 *       @OA\Property(property="company_id", type="string", example="2", description="The hashed id of the company"),
 *       @OA\Property(property="client_id", type="string", example="2", description="The hashed_id of the client"),
 *       @OA\Property(property="token", type="string", example="2", description="The payment token"),
 *       @OA\Property(property="routing_number", type="string", example="2", description="THe bank account routing number"),
 *       @OA\Property(property="company_gateway_id", type="string", example="2", description="The hashed id of the company gateway"),
 *       @OA\Property(property="is_default", type="boolean", example="true", description="Flag determining if the token is the default payment method"),
 *
 * )
 */
