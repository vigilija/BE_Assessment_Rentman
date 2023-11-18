<?php
namespace Assessment\Availability\Todo;

use Assessment\Availability\EquimentAvailabilityHelper;
use DateTime;
use Exception;
use PDO;

class EquimentAvailabilityHelperAssessment extends EquimentAvailabilityHelper {

	private PDO $pdo;
	/**
	 * This function checks if a given quantity is available in the passed time frame
	 * @param int      $equipment_id Id of the equipment item
	 * @param int      $quantity How much should be available
	 * @param DateTime $start Start of time window
	 * @param DateTime $end End of time window
	 * @return bool True if available, false otherwise
	 */


	public function __construct(PDO $oDatabaseConnection) {
        parent::__construct($oDatabaseConnection);
		$this->$pdo = $this->getDatabaseConnection();
    }

	public function isAvailable(int $equipment_id, int $quantity, DateTime $start, DateTime $end) : bool {
	
		try {
		$query = $this->$pdo->prepare(		
            'SELECT SUM(quantity) as total_quantity 
            FROM planning 
            WHERE equipment = :equipment_id 
            AND ((start BETWEEN :start AND :end) OR (end BETWEEN :start AND :end))'
        );

        $query->execute([
            'equipment_id' => $equipment_id,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);

        $result = $query->fetchColumn();
		} catch (PDOException $e) {
			echo 'Database connection error: ' . $e->getMessage();
		}
		
         return ($result + $quantity) <= $this->getEquipmentStock($equipment_id);
	}

	/**
	 * Calculate all items that are short in the given period
	 * @param DateTime $start Start of time window
	 * @param DateTime $end End of time window
	 * @return array Key/valyue array with as indices the equipment id's and as values the shortages
	 */
	public function getShortages(DateTime $start, DateTime $end) : array {

		$query = $this->$pdo->prepare(
            'SELECT equipment, MAX(stock - total_quantity) as shortage 
            FROM (
                SELECT e.id as equipment, e.stock, SUM(p.quantity) as total_quantity
                FROM equipment e
                LEFT JOIN planning p ON e.id = p.equipment
                WHERE (p.start BETWEEN :start AND :end) OR (p.end BETWEEN :start AND :end)
                GROUP BY e.id
            ) as aggregated
            GROUP BY equipment
			HAVING shortage < 0'
        );

        $query->execute([
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);

        return $query->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	private function getEquipmentStock($equipment_id): int
    {
		$pdo = $this->getDatabaseConnection();
		try {
        $query = $pdo->prepare('SELECT stock FROM equipment WHERE id = :equipment_id');
        $query->execute(['equipment_id' => $equipment_id]);
        
	} catch (PDOException $e) {
		// If an exception is caught, there's an issue with the connection
		echo 'Database connection error 2: ' . $e->getMessage();
	}
	return (int)$query->fetchColumn();
    }

}
