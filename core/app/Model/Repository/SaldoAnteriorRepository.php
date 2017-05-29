<?php
abstract class SaldoAnteriorRepository
{
	public $banco;
	public $saldo;
	
	public function __construct() {
		
		global $array_db;
		
		$db = $array_db['db'];

		$this->banco = Banco::conecta('pgsql',$db);
	}
	
	public function verificaExisteSaldoAnterior() {
		$sql = "SELECT COUNT(*) AS total
				FROM banco.tb_saldo_anterior 
				WHERE( fk_conta_corrente = '".$this->GetFkContaCorrente()."' )";
		$res = $this->banco->executar( $sql );
		
		if( empty( $res[0]['total'] ) ) {
			$dados['fk_conta_corrente'] = $this->GetFkContaCorrente();
			$dados['sda_data'] = $this->GetSdaData();
			$dados['sda_valor'] = 0;
			$this->banco->inserir( 'banco.tb_saldo_anterior', $dados );
		}
		
		return true;
	}
	
	public function verificaExisteSaldo() {
		$sql = "SELECT COUNT(*) AS total
				FROM banco.tb_saldo_anterior 
				WHERE( fk_conta_corrente = '".$this->GetFkContaCorrente()."' ) AND 
						( to_char( tb_saldo_anterior.sda_data, 'YYYY-MM-DD')::text = '".$this->GetSdaData()."' )";
		$res = $this->banco->executar( $sql );
		
		if( $res ) {
			if( !empty( $res[0]['total'] ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	public function getSaldoDiaAnterior() {
		$sql = "SELECT sda_valor 
				FROM banco.tb_saldo_anterior 
				WHERE( fk_conta_corrente = '".$this->GetFkContaCorrente()."' ) AND 
					( to_char( tb_saldo_anterior.sda_data, 'YYYY-MM-DD')::text = '".$this->GetSdaData()."' )";
		$res = $this->banco->executar( $sql );
		
		if( $res ) {
			return $res[0]['sda_valor'];
		} else {
			return false;	
		}
	}
	
	public function processarSaldoConta() {
		$sql = "SELECT tb_movimento.mvm_valor, 
						tb_tipo_movimento.tmv_cred_deb 
					FROM banco.tb_movimento 
					INNER JOIN banco.tb_tipo_movimento ON banco.tb_tipo_movimento.tmv_id = banco.tb_movimento.fk_tipo_movimento 
					WHERE( banco.tb_movimento.fk_conta_corrente = '".$this->GetFkContaCorrente()."' ) AND 
						( to_char( tb_movimento.mvm_data, 'YYYY-MM-DD')::text = '".$this->GetSdaData()."' )";
						
		$res = $this->banco->executar( $sql );
		foreach( $res as $movimento ) {
			if( $movimento['tmv_cred_deb'] == 'c' ) {
				$this->saldo += $movimento['mvm_valor'];	
			}
			
			if( $movimento['tmv_cred_deb'] == 'd' ) {
				$this->saldo -= $movimento['mvm_valor'];
			}
		}
		
		$dados = array();
		if( $this->verificaExisteSaldo() ) { //existe o saldo, update
			$dados['sda_valor'] = $this->saldo;
			$this->banco->editar( 'banco.tb_saldo_anterior', $dados, "( tb_saldo_anterior.fk_conta_corrente = '".$this->GetFkContaCorrente()."' ) AND 
						( to_char( tb_saldo_anterior.sda_data, 'YYYY-MM-DD')::text = '".$this->GetSdaData()."' )" );
			//echo "update<br>";
		} else { //nao existe saldo, insert
			$dados['fk_conta_corrente'] = $this->GetFkContaCorrente();
			$dados['sda_data'] = $this->GetSdaData();
			$dados['sda_valor'] = $this->saldo;
			$this->banco->inserir( 'banco.tb_saldo_anterior', $dados );
			//echo "insert<br>";
		}
		
		return true;
	 }

	public function getDiaMenosUm( $data ) {
		$time = strtotime( $data." 00:00:00" );		
		$timeOntem = $time - 86400;
		$data = getdate( $timeOntem );
		$mes = ( $data['mon'] < 10 ) ? "0".$data['mon'] : $data['mon'];
		$dia = ( $data['mday'] < 10 ) ? "0".$data['mday'] : $data['mday'];		
		$data = $data['year']."-".$mes."-".$dia;
		
		return $data;	
	}
	 
	 public function getSaldoAnterior() {
		$data = $this->GetSdaData();
		$data = substr( $data, 6, 4 )."-".substr( $data, 3, 2 )."-".substr( $data, 0, 2 );
		$data = $this->getDiaMenosUm( $data ); //dia anterior no formato YYYY-MM-DD
		
		$encontrou = false;
		
		while( !$encontrou ) {
			$sql = "SELECT sda_valor, 
						( to_char( sda_data, 'DD/MM/YYYY' ) ) AS sda_data  
					FROM banco.tb_saldo_anterior 
					WHERE( sda_data = '".$data."' ) AND 
						( fk_conta_corrente = '".$this->GetFkContaCorrente()."' )";	

			$res = $this->banco->executar( $sql );
			if( isset( $res[0] ) ) {
				$encontrou = true;	
			} else {
				//$data = $this->getDiaMenosUm( $data ); //dia anterior no formato YYYY-MM-DD
				$encontrou = true;
				$res[0]['sda_data'] = substr( $data, 8, 2)."/".substr( $data, 5, 2 )."/".substr( $data, 0, 4 );
			}
		}
		
		//print_r( $res ); die;
		return $res[0];
	 }

	public function desconecta() {
		Banco::desconecta_pgsql();
	}	
}