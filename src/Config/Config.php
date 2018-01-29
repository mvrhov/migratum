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

namespace Migratum\Config;


/**
 * short description
 *
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 */
class Config
{
    /** @var Environment */
    protected $default;

    /** @var Environment[] */
    protected $environments;

    public function getDefault(): Environment
    {
        return $this->default;
    }

    public function setDefault(Environment $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function createEnvironment(string $name): Environment
    {
        $e = new Environment($name);

        $this->environments[$name] = $e;

        return $e;
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    public function getEnvironment(string $name): Environment
    {
        if (!isset($this->environments[$name])) {
            throw new \RuntimeException(sprintf('There is no environment named \'%s\'', $name));
        }

        return $this->environments[$name];
    }
}
