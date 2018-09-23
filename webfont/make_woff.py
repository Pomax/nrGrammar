#!/usr/bin/env python3
import sys
from sys import exit, argv
from pathlib import Path
import json
import logging


def error(*args):
    print(*args, file=sys.stderr)


if sys.version_info[0:2] < (3, 6):
    error("This script requires Python version 3.6 or later.")
    exit(1)


try:
    import fontTools.ttLib
    import fontTools.subset
    import fontTools.merge
    import fontTools.misc.loggingTools
except ImportError:
    error("Please install the fontTools python module.")
    exit(1)

_USAGE = f"""usage: {argv[0]} conffile

conffile is in JSON format. Example with explanatory comments (don't include
these comments in the JSON file):
{{
  // List of files or globs relative to the config file's dir.
  "dataFiles": [
    "../data/pages/en-GB/**/*.txt"
  ],
  // List of individual code points and ranges (list with start an end).
  "excludeCodepoints": [
    [0, 127]
  ],
  // The order of fonts are important, fonts will be checked for every code
  // point until one has a glyph. First element is the input, second is the
  // output.
  "fonts": [
    ["HanaMinA.ttf", "../HanaMinA.woff"],
    ["HanaMinB.ttf", "../HanaMinB.woff"]
  ],
  // Missing characters will be written to this file in utf-8. relative to
  // config file's location.
  "missingOutput": "missing.txt",
  // if true, only print which fonts are used and missing characters
  "justCheck": false
}}"""


class Font:
    """Wrapper around fontTools.ttLib.TTFont"""
    def __init__(self, font=None):
        self.unicode_subset = set()
        self.ttf = None
        self.fontpath = ""
        self.cmap = dict()
        self.family_name = ""
        if font:
            self.load(font)

    def __str__(self):
        return f'{self.familyname} ({self.fontpath})'

    def load(self, font):
        """Open font file or add TTFont instance"""
        if isinstance(font, fontTools.ttLib.TTFont):
            self.ttf = font
        else:
            try:
                self.ttf = fontTools.ttLib.TTFont(font, recalcBBoxes=False)
            except FileNotFoundError as err:
                error(f'Font file "{font}" not found: {err.strerror}')
                exit(1)
            except OSError as err:
                error(f'Error reading "{font}": {err.strerror}')
                exit(1)
            self.fontpath = font
        self.cmap = self.ttf.getBestCmap()
        self.familyname = self.ttf['name'].getDebugName(1)

    def check_unicode(self, codepoint):
        """Check if codepoint has a glyph."""
        return codepoint in self.cmap

    def add_unicode(self, codepoint):
        """Add codepoint to the subset list if found in the font.

        Returns True if the font has a glyph, False if it doesn't.
        """
        if self.check_unicode(codepoint):
            self.unicode_subset.add(codepoint)
            return True
        else:
            return False

    def subset_len(self):
        return len(self.unicode_subset)

    def subset(self):
        subsetter = fontTools.subset.Subsetter()
        subsetter.populate(unicodes=self.unicode_subset)
        subsetter.subset(self.ttf)

    def save(self, outpath):
        self.ttf.flavor = 'woff'
        try:
            self.ttf.save(outpath)
        except OSError as err:
            error(f'Error creating "{outpath}": {err.strerror}')
            exit(1)


class MissingGlyphError(Exception):
    def __init__(self, codepoint):
        self.codepoint = codepoint

    def __str__(self):
        return f"Can't find glyph for U+{self.codepoint:04X}"


class FilterPipeLine():
    def __init__(self):
        self.filters = []

    def add_filter(self, filt):
        self.filters.append(filt)

    def remove_filter(self, filt):
        self.filters.remove(filt)

    def feed(self, codepoint):
        """Feed codepoint or unicode character."""
        if not isinstance(codepoint, int):
            codepoint = ord(codepoint)
        for filt in self.filters:
            if filt.feed(codepoint):
                break
        else:
            raise MissingGlyphError(codepoint)


class BaseFilter():
    def feed(self, codepoint):
        """Let a subclassing filter handle the codepoint.

        Returning True means that the codepoint was handled, subsequent filters
        will be skipped.

        """
        raise NotImplementedError


class FontFilter(BaseFilter):
    def __init__(self, font):
        self.font = font

    def feed(self, codepoint):
        return self.font.add_unicode(codepoint)


class UniqueFilter(BaseFilter):
    """Skips already used code points.

    Should be the first one.
    """
    def __init__(self):
        self.fed = set()

    def feed(self, codepoint):
        if codepoint in self.fed:
            return True
        else:
            self.fed.add(codepoint)
            return False


class ExcludeFilter(BaseFilter):
    def __init__(self):
        self.exclude_ranges = []

    def exclude_range(self, unicode_range):
        self.exclude_ranges.append(unicode_range)

    def feed(self, codepoint):
        for range_ in self.exclude_ranges:
            if codepoint in range_:
                return True
            else:
                return False


def main():
    argc = len(argv)
    if argc == 1:
        print(_USAGE)
        exit(0)
    elif argc == 2:
        if argv[1] in ['-h', '--help']:
            print(_USAGE)
            exit(0)
        else:
            try:
                configpath = argv[1]
                with open(configpath) as configfile:
                    config = json.load(configfile)
            except FileNotFoundError as err:
                error(f'Config file "{configpath}" not found: {err.strerror}')
                exit(1)
            except OSError as err:
                error(f'Error reading "{configpath}": {err.strerror}')
                exit(1)
            except json.JSONDecodeError as err:
                error(f'JSON error in "{configpath}": {err}')
                exit(1)
            configdir = Path(argv[1]).parent

    ft_logger = logging.getLogger('fontTools.subset')

    def silence_unknown_subset_table(record):
        return record.msg != "%s NOT subset; don't know how to subset; dropped"

    ft_logger.addFilter(silence_unknown_subset_table)

    pipeline = FilterPipeLine()

    uniq_filt = UniqueFilter()
    pipeline.add_filter(uniq_filt)

    excl_filt = ExcludeFilter()
    for excl in config['excludeCodepoints']:
        if isinstance(excl, list):
            excl_filt.exclude_range(range(excl[0], excl[1]))
        else:
            excl_filt.exclude_range(range(excl, excl+1))
    pipeline.add_filter(excl_filt)

    fonts = []
    for font_conf in config['fonts']:
        infile = configdir / font_conf[0]
        outfile = configdir / font_conf[1]
        font = Font(infile)
        font.outfile = outfile
        fonts.append(font)
    font_filts = map(FontFilter, fonts)
    for filt in font_filts:
        pipeline.add_filter(filt)

    paths = []
    for pattern in config['dataFiles']:
        paths.extend(configdir.glob(pattern))

    missing = set()

    for path in paths:
        with path.open(encoding='utf_8') as file:
            c = file.read(1)
            while c:
                try:
                    pipeline.feed(c)
                except MissingGlyphError as err:
                    missing.add(err.codepoint)
                c = file.read(1)

    for font in fonts:
        print(f'  {font.subset_len():>6d} chars from {font}')

    if missing:
        missing_path = configdir / config['missingOutput']
        try:
            with missing_path.open('w', encoding='utf_8') as mfile:
                for codepoint in missing:
                    mfile.write(chr(codepoint))
        except OSError as err:
            error(f'Error writing "{missing_path}": {err.strerror}')
        print(f"{len(missing)} chars were written to {missing_path}")

    just_check = config['justCheck']
    if just_check:
        exit(0)

    for font in fonts:
        if font.subset_len():
            font.subset()
            font.save(font.outfile)
            print(f'Webfont saved as {font.outfile}.')


if __name__ == '__main__':
    main()
