<?php

interface Payment_Method
{
	public function get_params();

	public function get_description();
}
