<?php

namespace Nuvede\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;
use PHP_CodeSniffer\Sniffs\Sniff;


class ClassSpacingSniff implements Sniff
{

    public function register(): array
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }


    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // Las clases anónimas y las declaraciones sin cuerpo no se evalúan.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $opener = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['scope_closer'];

        $elements = $this->findClassElements($phpcsFile, $stackPtr, $opener, $closer);

        $this->checkGapFromImports($phpcsFile, $stackPtr);
        $this->checkAfterOpener($phpcsFile, $opener, $elements);
        $this->checkBeforeCloser($phpcsFile, $opener, $closer);
        $this->checkElementGaps($phpcsFile, $elements);
    }


    private function findClassElements(File $phpcsFile, int $classPtr, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $elements = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            $code = $tokens[$i]['code'];

            $isCandidate = $code === T_FUNCTION || $code === T_CONST || $code === T_USE || $code === T_VARIABLE;
            if ($isCandidate === false) {
                continue;
            }

            // Solo elementos directos de la clase.
            $conditions = $tokens[$i]['conditions'];
            end($conditions);
            if (key($conditions) !== $classPtr) {
                continue;
            }

            // Los parámetros de los métodos no son atributos de la clase.
            if ($code === T_VARIABLE && isset($tokens[$i]['nested_parenthesis'])) {
                continue;
            }

            $kind = match ($code) {
                T_FUNCTION => 'method',
                T_CONST => 'constant',
                T_USE => 'trait_use',
                T_VARIABLE => 'property',
            };

            $end = $code === T_FUNCTION && isset($tokens[$i]['scope_closer'])
                ? $tokens[$i]['scope_closer']
                : $phpcsFile->findEndOfStatement($i);

            $elements[] = [
                'kind' => $kind,
                'start' => $this->findElementStart($phpcsFile, $i),
                'end' => $end,
            ];

            $i = $end;
        }

        return $elements;
    }


    private function findElementStart(File $phpcsFile, int $ptr): int
    {
        $tokens = $phpcsFile->getTokens();

        $start = $phpcsFile->findStartOfStatement($ptr);

        // Un comentario pegado en la línea de arriba forma parte del elemento.
        while (true) {
            $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($start - 1), null, true);
            if ($prev === false) {
                break;
            }

            $prevIsComment = isset(Tokens::$commentTokens[$tokens[$prev]['code']]);
            $prevIsAttached = $tokens[$prev]['line'] === ($tokens[$start]['line'] - 1);
            if ($prevIsComment === false || $prevIsAttached === false) {
                break;
            }

            if ($tokens[$prev]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
                $prev = $tokens[$prev]['comment_opener'];
            }

            $start = $prev;
        }

        return $start;
    }


    private function checkGapFromImports(File $phpcsFile, int $classPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $declStart = $phpcsFile->findStartOfStatement($classPtr);

        $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($declStart - 1), null, true);
        if ($prev === false || $tokens[$prev]['code'] !== T_SEMICOLON) {
            return;
        }

        $stmtStart = $phpcsFile->findStartOfStatement($prev);
        if ($tokens[$stmtStart]['code'] !== T_USE) {
            return;
        }

        $blank = $tokens[$declStart]['line'] - $tokens[$prev]['line'] - 1;
        if ($blank !== 2) {
            $phpcsFile->addError(
                'Debe haber dos líneas en blanco entre el bloque de use y la clase; hay %d.',
                $declStart,
                'BlankLinesBeforeClass',
                [$blank]
            );
        }
    }


    private function checkAfterOpener(File $phpcsFile, int $opener, array $elements): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($elements === []) {
            return;
        }

        $first = $elements[0];
        $blank = $tokens[$first['start']]['line'] - $tokens[$opener]['line'] - 1;

        // Cuando la clase empieza directo con un método se admite también el
        // espaciado de métodos (dos líneas en blanco).
        $isValid = $blank === 1 || ($first['kind'] === 'method' && $blank === 2);
        if ($isValid === false) {
            $phpcsFile->addError(
                'Debe haber una línea en blanco después de la llave de apertura de la clase; hay %d.',
                $first['start'],
                'BlankLineAfterOpener',
                [$blank]
            );
        }
    }


    private function checkBeforeCloser(File $phpcsFile, int $opener, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();

        $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($closer - 1), null, true);
        if ($prev === false || $prev === $opener) {
            return;
        }

        $blank = $tokens[$closer]['line'] - $tokens[$prev]['line'] - 1;
        if ($blank !== 1) {
            $phpcsFile->addError(
                'Debe haber una línea en blanco antes del cierre de la clase; hay %d.',
                $prev,
                'BlankLineBeforeCloser',
                [$blank]
            );
        }
    }


    private function checkElementGaps(File $phpcsFile, array $elements): void
    {
        $tokens = $phpcsFile->getTokens();

        $previous = null;
        foreach ($elements as $element) {
            if ($previous === null) {
                $previous = $element;

                continue;
            }

            $blank = $tokens[$element['start']]['line'] - $tokens[$previous['end']]['line'] - 1;

            if ($element['kind'] === 'method') {
                if ($blank !== 2) {
                    $phpcsFile->addError(
                        'Debe haber dos líneas en blanco antes de cada método; hay %d.',
                        $element['start'],
                        'BlankLinesBeforeMethod',
                        [$blank]
                    );
                }
            } elseif ($element['kind'] === $previous['kind']) {
                if ($blank > 1) {
                    $phpcsFile->addError(
                        'Los %s forman un solo bloque, con a lo sumo una línea en blanco entre ellos; hay %d.',
                        $element['start'],
                        'BlankLinesInsideBlock',
                        [$this->kindLabel($element['kind']), $blank]
                    );
                }
            } else {
                if ($blank !== 1) {
                    $phpcsFile->addError(
                        'Debe haber una línea en blanco entre el bloque de %s y el de %s; hay %d.',
                        $element['start'],
                        'BlankLineBetweenBlocks',
                        [$this->kindLabel($previous['kind']), $this->kindLabel($element['kind']), $blank]
                    );
                }
            }

            $previous = $element;
        }
    }


    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'trait_use' => 'use de traits',
            'constant' => 'constantes',
            'property' => 'atributos',
            'method' => 'métodos',
        };
    }

}
