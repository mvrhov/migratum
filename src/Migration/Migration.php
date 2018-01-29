<?php declare(strict_types=1);
/**
 * Released under the MIT License.
 *
 * Copyright (c) 2018 Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Migratum\Migration;


/**
 * migration DTO
 */
class Migration
{
    /** Migration version, must be string because we can run on 32bit php
     *
     * @var string
     */
    public $version;
    /**
     * Date when the migration was applied to db
     *
     * @var \DateTimeImmutable
     */
    public $appliedAt;
    /**
     * migration file checksum at the time the migration was run
     *
     * @var string
     */
    public $checksum = '';
    /** @var string */
    public $description = '';
    /** @var string */
    public $namespace = '';
    /** @var array */
    public $meta = [];
    /**
     * filename of migration
     *
     * @var string
     */
    public $file = '';
    /**
     * checksum of file as stored on disk
     * @var string
     */
    public $fileChecksum = '';
}
