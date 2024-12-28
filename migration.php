<?php

namespace Sprint\Migration;

use Idex\Core\Container;

class PTL_2259_20241106115319 extends Version
{
    protected $description = "PTL_2259 Деактивация пользователей по списку";

    public function up()
    {
        $timeLimit = 30; // Ограничение по времени на один шаг
        $sessKey = 'PTL_2259DeactivateUsersFromCSV';
        $cnt = (int)$_SESSION[$sessKey . '_cnt'];
        $startRow = (int)$_SESSION[$sessKey . '_startRow'];
        $allCount = (int)$_SESSION[$sessKey . '_all'];
        $csvFilePath = __DIR__ . '/PTL_2259_20241106115319_files/deactivatedUser.csv'; // Путь к CSV

        $ts = time();
        $needContinue = false;
        $cntOnStep = 0;

        // Открываем CSV файл
        $file = fopen($csvFilePath, 'r');
        if (!$file) {
            $this->out("Не удалось открыть CSV файл.");
            return;
        }

        $repo = Container::getUserService()->getRepo();
        $lineCount = 0; // Нулевой счетчик
        while (($row = fgetcsv($file)) !== false) {
            if ($lineCount >= $startRow) {
                if (time() < $ts + $timeLimit) { // Если время позволяет
                    $userId = (int)$row[0]; // Получаем ID из строки
                    if ($userId > 0) { // Проверка на непустую строку
                        // Получаем пользователя
                        $user = $repo->query()->where(['ID' => $userId])->select('ID', 'ACTIVE')->one();
                        if ($user && $user['ACTIVE'] === 'Y') {
                            $user['ACTIVE'] = 'N';
                            $repo->save($user);
                            $cnt++;
                            $cntOnStep++;
                        }
                    }
                    $startRow++;
                } else {
                    $needContinue = true;
                    break;
                }
            }
            $lineCount++;
        }

        fclose($file);

        $this->outProgress('Обработка ' . $cnt . ' пользователей (на шаге - ' . $cntOnStep . ')', $cnt, $allCount);

        // Проверяем нужно ли продолжить выполнение
        if ($cntOnStep && $needContinue) {
            $_SESSION[$sessKey . '_startRow'] = $startRow;
            $_SESSION[$sessKey . '_cnt'] = $cnt;
            $_SESSION[$sessKey . '_all'] = $allCount;
            $this->restart();
        } else {
            unset($_SESSION[$sessKey . '_cnt']);
            unset($_SESSION[$sessKey . '_startRow']);
            $this->out("Миграция завершена. Деактивировано пользователей: {$cnt}");
        }
    }

    public function down()
    {
        //your code ...
    }
}
