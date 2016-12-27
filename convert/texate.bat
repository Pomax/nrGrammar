@echo off
title Texate
cls
if "%1" == "--help" goto help
php texate.php %*
if exist texated.pdf (
echo Opening pdf file
texated.pdf
)
goto end
:help
echo.
echo Run: texate [flags]
echo Available flags (order not important):
echo.
echo   --nodraft		runs the publication conversion rather than the draft conversions
echo   --preface name	compiles with specific preface
echo.
echo   --runlatex		runs the xelatex compile step
echo   --noconvert		skips conversion from dokuwiki to tex step
echo   --noquote		doesn't make quotation, elision and commas look pretty
echo   --nounlink		does not delete the extra files latex builds during its run
echo   --stepthrough		write separate files for all the intermediate conversion stages
echo   --outfile name	compiles to the specified filename (do not add a file extension)
echo.
echo By default, texate will run only the conversion step for dokuwiki to Tex format.
echo The most common use is as "texate --runlatex" to do both conversion and compilation
echo through a single command.
echo.
:end
