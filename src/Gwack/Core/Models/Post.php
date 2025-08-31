<?php

use Gwack\Core\Database\Model;

/**
 * Post Model
 *
 * Example model for demonstration
 */
class Post extends Model
{
    protected static string $table = 'posts';
    protected array $fillable = ['title', 'content', 'user_id'];
}
