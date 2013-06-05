# HelloPHP

A rudimentary reverse proxy for PHP.

Proxies requests to other servers.

Usage of this script should be taken with great care (and used only in dire circumstances).

The performance characteristics are shaky at best. I really mean it when I say, "use only in dire circumstances".

## Usage

Read `configuration.example.php`, copy it to `configuration.php`.

Read `index.php`.

Set up `.htaccess` to rewrite everything into your script.

## Why??????

Once upon a time, I had to deploy a WordPress installation on a particularly hostile environment. There were no militant groups trying to stop me at gunpoint, but it was close. PHP 5.1, no database, no write access to the filesystem (not even to /tmp!).

And WordPress was a must.

An offsite database worked until the problem of not being able to save images hit us.

Then, it struck me! I'll just use a silly little script to forward all requests to a much better server that I have complete control over. Goodbye PHP 5.1 troubles, hello WordPress 3.5.

## Contributing

Your contributions will be very welcome! Don't be shy to send a pull request.

## License

Copyright © 2013 Keitaroh Kobayashi.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.