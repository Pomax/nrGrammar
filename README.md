An introduction to Japanese - Syntax, Grammar & Language
========================================================

This is the repository for the data that is used to
generate the book "An introduction to Japanese",
previously found at http://www.amazon.com/dp/9081507117
and other stores.

I've taken this book off the market (because I cancelled
my account with my publisher and will be looking at a
new way to do publishing) but the book is still available
in PDF form.

I've been running it as a dokuwiki for a few years on
http://grammar.nihongoresources.com and that has proven
to be useful, but progressively harder to maintain as
dokuwiki changes its APIs, and my content relies on
specific dokuwiki plugins for which I don't have time
to actually update them to work with the new versions.

So, I'll be trying to rewrite it to a self-contained
website that you can file github issues for, with a
php compiler to turn the raw data into .pdf data.

Interesting fact: just because someone's front door
is open doesn't mean you have the right to take their
stuff, and in the same vein: just because I'm hosting
this on a public repository does not give you the right
to use the code and raw data for your own purposes.

This book is [free for the general public in PDF form](data/pdf/draft.pdf),
and available as the more traditional (affordable, gasp!)
paper textbook version at book retailers, but this is
a product, not a project: all code and data is owned by
me, Mike "Pomax" Kamermans, and I reserve all rights.
You expressly do not have permission to start compiling
your own version (except to test the compilation from
plain text to .pdf), nor do you have permission to
distribute this code or data yourself.

You are, however, welcome to help improve the text,
or suggesting compile improvements, by filing issues
or submitting pull requests. Contributions deserve
acknowledgements in the book's acknowledgement section.

Live site: [https://Pomax.github.io/nrGrammar](https://Pomax.github.io/nrGrammar)

## Development

Compilation of book relies on some older technologies in
part because it was initially created back in 2008 when
PHP was still the king of "get shit done fast" scripting
langauges for people who needed one scripting language
to do everything from CLI to web content (was has since
been replaced by JavaScript), and LuaTex was still
four years away from a version 1, making the only
sane TeX choice XeLaTeX (because XeTeX is natively
utf8, rather than needing special instructions just
to understand utf8 documents).

## Localisation

Localised content is housed in `./data/pages`, where each
locale uses its own locale code as directory name. Inside
of those, all files should follow the filename convention
as used in the `en-GB` directory, in order for the compilation
scripts to find them.

Translations should follow the spirit of the text, not the
letter of text: if an idiom or illustratrive piece of text
does not work in the language you're targeting, *please do not
translate it literally*. The worst thing you can do when
translating is to take text that works in one language, and
force-translating it to a weird, clunky text in a different
language. Sometimes that might mean rewriting an entire
paragraph, or even more than one, in order for the discourse
to flow naturally in your target language: that is fine.
It is in fact infinitely preferable over a literal translation
that isn't up to whatever is considered university-level
langauge in the country/region you're localizing for.
