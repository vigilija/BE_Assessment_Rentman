<?php
namespace Assessment\Availability\Todo;

use Assessment\Availability\EquimentAvailabilityHelper;
use DateTime;
use Exception;
use PDO;

class EquimentAvailabilityHelperAssessment extends EquimentAvailabilityHelper {

	/**
	 * EquimentAvailabilityHelperAssessment constructor. Calls parent class constructor.
	 * @param PDO $oDatabaseConnection
	 */

	public function __construct(PDO $oDatabaseConnection) {
        parent::__construct($oDatabaseConnection);
		$this->$pdo2 = $this->getDatabaseConnection();
    }

	/**
	 * This function checks if a given quantity is available in the passed time frame
	 * @param int      $equipment_id Id of the equipment item
	 * @param int      $quantity How much should be available
	 * @param DateTime $start Start of time window
	 * @param DateTime $end End of time window
	 * @return bool True if available, false otherwise
	 */

	public function isAvailable(int $equipment_id, int $quantity, DateTime $start, DateTime $end) : bool {
		try {
			$result = true;
			$check_date = $start;

			//Loop through each day (between start and end dates) and check planed_quantity < quantity
			while ($result && $check_date <= $end){

				$query = $this->$pdo2->prepare(		
					'SELECT SUM(quantity) as total_quantity 
					FROM planning 
					WHERE equipment = :equipment_id 
					AND (:check_date BETWEEN start AND end)'
				);

				$query->execute([
					'equipment_id' => $equipment_id,
					'check_date' => $check_date->format('Y-m-d')
				]);

				$planed_quantity = $query->fetchColumn();
				$result = ($planed_quantity + $quantity) <= $this->getEquipmentStock($equipment_id);
				$check_date ->modify('+1 day');
			}

		} catch (PDOException $e) {
				throw new Exception('Database connection error: ' . $e->getMessage());			
		}		
    	return $result;
	}


	/**
	 * Calculate all items that are short in the given period
	 * @param DateTime $start Start of time window
	 * @param DateTime $end End of time window
	 * @return array Key/valyue array with as indices the equipment id's and as values the shortages
	 */
	public function getShortages(DateTime $start, DateTime $end) : array {
		try {
			$query = $this->$pdo->prepare(
				'SELECT equipment_id, (stock - total_reserved_quantity) as shortage
				FROM (
						SELECT e.id as equipment_id, e.stock, SUM(p.quantity) as total_reserved_quantity
						FROM equipment e
							LEFT JOIN planning p ON e.id = p.equipment
						WHERE (p.start BETWEEN :start AND :end) OR (p.end BETWEEN :start AND :end)
						GROUP BY e.id
				) as aggregated
				WHERE (stock - total_reserved_quantity)< 0'
			);
			$query->execute([
				'start' => $start->format('Y-m-d'),
				'end' => $end->format('Y-m-d'),
			]);
			
		}catch(PDOException $e){
				// If an exception is caught, there's an issue with the connection
				throw new Exception('Database connection error: ' . $e->getMessage());
		}		
        return $query->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	/**
	 * This function returns stock quantity is available for equipment
	 * @param int      $equipment_id Id of the equipment item
	 * @return int 	Stock quantity 
	 */
	private function getEquipmentStock($equipment_id): int
    {
		try {
        	$query = $this->$pdo->prepare('SELECT stock FROM equipment WHERE id = :equipment_id');
        	$query->execute([':equipment_id' => $equipment_id]);
        
		} catch (PDOException $e) {
			// If an exception is caught, there's an issue with the connection
			throw new Exception('Database connection error: ' . $e->getMessage());
		}
	return (int)$query->fetchColumn();
    }

}
